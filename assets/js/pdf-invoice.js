(function($){
  // NOTE: For proper Serbian Latin characters (č,ć,ž,š,đ) use a Unicode font like DejaVuSans.
  // Place DejaVuSans.ttf into assets/fonts/DejaVuSans.ttf. jsPDF will embed it dynamically below.
  // If the font file is missing, jsPDF will fall back to its core font and some glyphs may look wrong.
  function textRight(doc, text, xRight, y){
    var width = doc.getTextWidth(text);
    doc.text(text, xRight - width, y);
  }

  function sendToServer(filename, title, pdfDataUri){
    var ajaxUrl = window.SU_INVOICE_AJAX_URL || window.ajaxurl || '/wp-admin/admin-ajax.php';
    var $btn = $('#su-download-invoice');
    var orderId = $btn.data('order-id');
    var nonce = $btn.data('nonce');
    if(!orderId || !nonce){ return; }
    return $.ajax({
      url: ajaxUrl,
      method: 'POST',
      data: {
        action: 'su_save_invoice_pdf',
        order_id: orderId,
        nonce: nonce,
        title: title,
        filename: filename,
        pdf: pdfDataUri
      }
    });
  }

  function fetchInvoiceNumber(){
    var $btn = $('#su-download-invoice');
    var orderId = $btn.data('order-id');
    var nonce = $btn.data('nonce');
    var docType = $btn.data('doc-type');
    if(!orderId || !nonce){ return $.Deferred().reject('Missing order id/nonce').promise(); }
    return $.ajax({
      url: window.SU_INVOICE_AJAX_URL || window.ajaxurl || '/wp-admin/admin-ajax.php',
      method: 'POST',
      data: { action: 'su_get_invoice_number', order_id: orderId, nonce: nonce, doc_type: docType }
    });
  }

  function localizeDate(str){
    if(!str) return str;
    var map = {
      'January':'januar','February':'februar','March':'mart','April':'april','May':'maj','June':'jun','July':'jul','August':'avgust','September':'septembar','October':'oktobar','November':'novembar','December':'decembar'
    };
    Object.keys(map).forEach(function(en){
      str = str.replace(en, map[en]);
    });
    return str;
  }

  function ensureFont(doc, done){
    var ctx = window.SU_PDF_CTX || {};
    var fontUrl = (ctx.SU_FONTS_PATH ? ctx.SU_FONTS_PATH : '') + '/DejaVuSans.ttf';
    if(!fontUrl || fontUrl.indexOf('http') !== 0){ return done(); }
    // Fetch and register font
    fetch(fontUrl).then(function(r){ return r.arrayBuffer(); })
      .then(function(buf){
        try {
          var uint8 = new Uint8Array(buf);
          doc.addFileToVFS('DejaVuSans.ttf', uint8);
          doc.addFont('DejaVuSans.ttf','DejaVu','normal');
          doc.setFont('DejaVu','normal');
        } catch(e){ console.warn('Font add failed', e); }
        done();
      })
      .catch(function(){ done(); });
  }

  function buildPdf(data, invoiceNumber){
    if(!window.jspdf || !window.jspdf.jsPDF){ alert('PDF engine nije dostupan.'); return; }
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({ unit: 'mm', format: 'a4' });

    var margin = 15;
    var pageWidth = 210; // A4 width in mm
    var pageHeight = 297; // A4 height in mm

    // Convert date strings to localized forms
    if(data && data.meta){
      data.meta.invoice_date = localizeDate(data.meta.invoice_date);
      data.meta.order_date   = localizeDate(data.meta.order_date);
    }

    // Logo at top left (if available via context)
    var ctx = window.SU_PDF_CTX || {};
    if(ctx.SU_LOGO_PATH){
      try {
        doc.addImage(ctx.SU_LOGO_PATH, 'PNG', margin, margin, 40, 0);
      } catch(e) {
        console.warn('Logo failed to load:', e);
      }
    }

    // Seller info top-right
    doc.setFont('helvetica','normal');
    doc.setFontSize(8);
    var sellerLines = [
      data.seller.name,
      data.seller.address,
      '11000 Beograd',
      'Srbija',
      'delatnost i sifra delatnosti: 8559 - Ostalo obrazovanje',
      'maticni broj: 21848891',
      'poreski broj: 113341376'
    ];
    var sellerY = margin;
    sellerLines.forEach(function(line){ 
      var textWidth = doc.getTextWidth(line);
      doc.text(line, pageWidth - margin - textWidth, sellerY); 
      sellerY += 4.5; 
    });

    // Document title (centered, bold)
    var titleY = 60;
    doc.setFont('helvetica','bold');
    doc.setFontSize(18);
    var title = (data.docTitle || 'FAKTURA').toUpperCase();
    var titleWidth = doc.getTextWidth(title);
    doc.text(title, (pageWidth - titleWidth) / 2, titleY);

    // Buyer section (left)
    var buyerY = 80;
    doc.setFont('helvetica','bold');
    doc.setFontSize(9);
    doc.text('Kupac:', margin, buyerY);
    
    buyerY += 5;
    doc.setFont('helvetica','normal');
    (data.buyer && data.buyer.lines || []).forEach(function(line){
      doc.text(String(line), margin, buyerY);
      buyerY += 5;
    });

    // Invoice details table (right)
    var detailsStartY = 80;
    var meta = data.meta || {};
    if(invoiceNumber){ meta.invoice_no = invoiceNumber; }
    
    if(doc.autoTable){
      doc.autoTable({
        startY: detailsStartY,
        body: [
          ['Broj fakture:', meta.invoice_no || ''],
          ['Datum fakture:', meta.invoice_date || ''],
          ['Broj porudzbine:', meta.order_no || ''],
          ['Datum porudzbine:', meta.order_date || ''],
          ['Nacin placanja:', meta.payment_method || '']
        ],
        theme: 'grid',
        styles: { 
          font: 'helvetica', 
          fontSize: 9, 
          cellPadding: 3,
          lineColor: [51, 51, 51],
          lineWidth: 0.1
        },
        columnStyles: {
          0: { fontStyle: 'bold', cellWidth: 50, lineColor: [204, 204, 204] },
          1: { cellWidth: 40 }
        },
        margin: { left: 105 },
        tableWidth: 90,
        didParseCell: function(data) {
          // Alternating row colors
          if(data.section === 'body' && data.row.index % 2 === 1){
            data.cell.styles.fillColor = [249, 249, 249];
          }
        }
      });
    }

    // Items table
    var itemsStartY = 145;
    
    // Clean prices from HTML entities
    var body = (data.items||[]).map(function(it){
      var name = it.name || '';
      if(it.sku){ name += "\nSifra proizvoda: " + it.sku; }
      // Strip HTML entities like &nbsp; from price
      var cleanPrice = (it.total || '').replace(/&nbsp;/g, ' ').replace(/&[a-z]+;/gi, '');
      return [ name, it.qty || 1, cleanPrice ];
    });

    if(doc.autoTable){
      doc.autoTable({
        startY: itemsStartY,
        head: [['Proizvod','Kolicina','Cena']],
        body: body,
        theme: 'grid',
        styles: { 
          font: 'helvetica', 
          fontSize: 9, 
          cellPadding: 6,
          lineColor: [0, 0, 0],
          lineWidth: 0.5
        },
        headStyles: { 
          fillColor: [51, 51, 51], 
          textColor: 255, 
          fontStyle: 'bold', 
          fontSize: 9,
          halign: 'left'
        },
        columnStyles: { 
          0: { cellWidth: 105, halign: 'left' },
          1: { cellWidth: 30, halign: 'center' }, 
          2: { cellWidth: 45, halign: 'right' } 
        },
        margin: { left: margin, right: margin },
        didParseCell: function(data) {
          // Alternating row colors
          if(data.section === 'body' && data.row.index % 2 === 1){
            data.cell.styles.fillColor = [249, 249, 249];
          }
        }
      });
      
      var tableEndY = doc.lastAutoTable.finalY;

      // Total section - only UKUPNO
      var totalY = tableEndY + 8;
      doc.setFont('helvetica','bold');
      doc.setFontSize(13);
      
      // Clean total from HTML entities
      var cleanTotal = (data.total || '').replace(/&nbsp;/g, ' ').replace(/&[a-z]+;/gi, '');
      
      var totalText = 'UKUPNO: ' + cleanTotal;
      var totalWidth = doc.getTextWidth(totalText);
      var totalX = pageWidth - margin - totalWidth;
      
      // Top border line
      doc.setDrawColor(0);
      doc.setLineWidth(0.5);
      doc.line(totalX, totalY - 2, pageWidth - margin, totalY - 2);
      
      doc.text(totalText, totalX, totalY + 5);

      // Footer
      var footerY = totalY + 15;
      doc.setDrawColor(0);
      doc.setLineWidth(0.3);
      doc.line(margin, footerY, pageWidth - margin, footerY);
      
      footerY += 5;
      doc.setFont('helvetica','italic');
      doc.setFontSize(8);
      var footerText = 'PDV je ukljucen u cenu.';
      var footerWidth = doc.getTextWidth(footerText);
      doc.text(footerText, (pageWidth - footerWidth) / 2, footerY);
    }

    var filename = (data.docTitle || 'faktura') + '_' + (invoiceNumber || meta.order_no || 'order') + '.pdf';

    // Save client-side and also post to server to persist under /Fakture
    var finalize = function(){
      var finalName = filename.toLowerCase();
      var dataUri = doc.output('datauristring');
      doc.save(finalName);
      sendToServer(finalName, data.docTitle || 'FAKTURA', dataUri)
        .done(function(res){
          if(res && res.success){
            console.log('PDF sacuvan na serveru:', res.data && res.data.url);
          } else { console.warn('Server nije vratio success za PDF:', res); }
        })
        .fail(function(jqXHR){ console.warn('Neuspesno slanje PDF-a na server. Status:', jqXHR.status); });
    };

    finalize();
  }

  function init(){
    var $btn = $('#su-download-invoice');
    if(!$btn.length){ return; }
    var jsonEl = document.getElementById('su-invoice-data');
    if(!jsonEl){ return; }
    var data = {};
    try { data = JSON.parse(jsonEl.textContent || '{}'); } catch (e) {}

    $btn.on('click', function(e){
      e.preventDefault();
      // First request/allocate invoice number, then build PDF with it
      fetchInvoiceNumber()
        .done(function(res){
          var invNo = res && res.success && res.data ? res.data.invoice_number : null;
          buildPdf(data, invNo);
        })
        .fail(function(){
          console.warn('Greška pri dobijanju broja fakture. Generišem bez broja.');
          buildPdf(data, null);
        });
    });
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})(jQuery);
