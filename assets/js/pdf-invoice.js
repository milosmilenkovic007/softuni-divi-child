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
    var doc = new jsPDF({ unit: 'pt', format: 'a4' });

    var margin = 54; // 3/4 inch
    var pageWidth = doc.internal.pageSize.getWidth();
    var xLeft = margin;
    var xRight = pageWidth - margin;
    var y = margin;

    // Convert date strings to localized forms
    if(data && data.meta){
      data.meta.invoice_date = localizeDate(data.meta.invoice_date);
      data.meta.order_date   = localizeDate(data.meta.order_date);
    }

    doc.setFont('helvetica','normal');
    doc.setFontSize(10);
    
    // Seller details top-right - simple bullet list
    var sellerLines = [
      data.seller.name,
      data.seller.address,
      '11000 Beograd',
      'Srbija',
      '• delatnost i sifra delatnosti: 8559 - Ostalo obrazovanje',
      '• maticni broj: 21848891',
      '• poreski broj: 113341376'
    ];
    sellerLines.forEach(function(line){ 
      textRight(doc, line, xRight, y); 
      y += 12; 
    });

    y += 18;
    // Title
    doc.setFont('helvetica','bold');
    doc.setFontSize(16);
    doc.text((data.docTitle || 'FAKTURA').toUpperCase(), xLeft, y);

    y += 24;
    doc.setFont('helvetica','normal');
    doc.setFontSize(10);

    // Left: buyer lines; Right: meta
    var leftY = y;
    (data.buyer && data.buyer.lines || []).forEach(function(line){
      doc.text(String(line), xLeft, leftY);
      leftY += 12;
    });

    var rightY = y;
    var meta = data.meta || {};
    var metaLines = [
      ['Broj fakture:', meta.invoice_no],
      ['Datum fakture:', meta.invoice_date],
      ['Broj porudzbine:', meta.order_no],
      ['Datum porudzbine:', meta.order_date],
      ['Nacin placanja:', meta.payment_method]
    ];
    metaLines.forEach(function(pair){
      if(!pair[1]) return;
      doc.text(pair[0], xRight-180, rightY);
      textRight(doc, String(pair[1]), xRight, rightY);
      rightY += 13;
    });

    y = Math.max(leftY, rightY) + 20;

    // Items table
    var body = (data.items||[]).map(function(it){
      var name = it.name || '';
      if(it.sku){ name += "\nSifra proizvoda: " + it.sku; }
      return [ name, it.qty || 1, it.total || '' ];
    });

    if(doc.autoTable){
      doc.autoTable({
        startY: y,
        head: [['Proizvod','Kolicina','Cena']],
        body: body,
        styles: { font: 'helvetica', fontSize: 9, cellPadding: 5, overflow: 'linebreak', lineColor: [0,0,0], lineWidth: 0.5 },
        headStyles: { fillColor: [0,0,0], textColor: 255, fontStyle: 'bold', fontSize: 10 },
        columnStyles: { 
          0: { cellWidth: 'auto' },
          1: { halign: 'center', cellWidth: 60 }, 
          2: { halign: 'right', cellWidth: 90 } 
        },
        theme: 'grid',
        margin: { left: xLeft, right: pageWidth - xRight }
      });
      y = doc.lastAutoTable.finalY + 16;
    } else {
      doc.setFont('helvetica','bold'); 
      doc.text('Proizvod', xLeft, y); 
      textRight(doc,'Cena', xRight, y); 
      y+=14; 
      doc.setFont('helvetica','normal');
      body.forEach(function(row){ 
        doc.text(String(row[0]), xLeft, y); 
        textRight(doc, String(row[2]), xRight, y); 
        y+=14; 
      });
      y += 12;
    }

    // Totals - right aligned
    doc.setFont('helvetica','normal');
    doc.setFontSize(10);
    var totalLabelX = xRight - 150;
    doc.text('Svega', totalLabelX, y); 
    textRight(doc, data.subtotal || '', xRight, y); 
    y += 14;
    doc.setFont('helvetica','bold');
    doc.text('Ukupno', totalLabelX, y); 
    textRight(doc, data.total || '', xRight, y);
    y += 30;

    // Footer note
    doc.setDrawColor(0); 
    doc.setLineWidth(0.5);
    doc.line(xLeft, y, xRight, y); 
    y += 20;
    doc.setFont('helvetica','normal'); 
    doc.setFontSize(9);
    var footerText = 'PDV je ukljucen u cenu.';
    doc.text(footerText, (pageWidth/2) - (doc.getTextWidth(footerText)/2), y);

  // Replace placeholder invoice_no with real sequential number
  if(invoiceNumber){ meta.invoice_no = invoiceNumber; }
  var filename = (data.docTitle || 'faktura') + '_' + (invoiceNumber || meta.order_no || 'order') + '.pdf';

    // Save client-side and also post to server to persist under /Fakture
    var finalize = function(){
      var finalName = filename.toLowerCase();
      var dataUri = doc.output('datauristring');
      doc.save(finalName);
      sendToServer(finalName, data.docTitle || 'FAKTURA', dataUri)
        .done(function(res){
          if(res && res.success){
            console.log('PDF sačuvan na serveru:', res.data && res.data.url);
          } else { console.warn('Server nije vratio success za PDF:', res); }
        })
        .fail(function(jqXHR){ console.warn('Neuspešno slanje PDF-a na server. Status:', jqXHR.status); });
    };

    ensureFont(doc, finalize);
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
