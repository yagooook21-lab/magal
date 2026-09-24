export interface ExtractedProduct {
  title: string;
  price: string;
  imageUrls: string[];
  characteristics: string;
  description: string;
  variations: string;
  reviews: string;
}

export function extractVariations(text: string) {
  const variacoes: any = {};
  let tipoDetectado = 'generico';

  if (!text) return { variacoes, tipoDetectado };

  // --- Voltagens ---
  const voltagens: string[] = [];
  if (/127\s*v/i.test(text)) voltagens.push('127v');
  if (/220\s*v/i.test(text)) voltagens.push('220v');
  if (/bivolt/i.test(text)) { voltagens.push('127v', '220v'); }
  if (voltagens.length > 0) {
    variacoes.voltagens = Array.from(new Set(voltagens));
    tipoDetectado = 'eletronico';
  }

  // --- RAM ---
  const ramMatches = text.match(/\b(\d+\s*GB)\s*(RAM|de RAM|memória RAM)/gi);
  if (ramMatches && ramMatches.length > 0) {
    variacoes.ram = Array.from(new Set(ramMatches.map(m => m.replace(/(RAM|de RAM|memória RAM)/gi, '').trim())));
    tipoDetectado = 'celular';
  }

  // --- Armazenamento ---
  const storageMatches = text.match(/\b(\d+\s*(GB|TB))\s*(armazenamento|de armazenamento|interno|de armazenamento interno)?/gi);
  if (storageMatches && storageMatches.length > 0) {
    const storageList = Array.from(new Set(storageMatches.map(m => m.replace(/(armazenamento|de armazenamento|interno|de armazenamento interno)/gi, '').trim()).filter(s => s.length > 0)));
    if (storageList.length > 0) {
      variacoes.armazenamento = storageList;
      if (tipoDetectado === 'generico') tipoDetectado = 'celular';
    }
  }

  // --- Tamanhos de roupa ---
  const tamanhosRoupa: string[] = [];
  const contextosTamanho = text.match(/(?:tamanho|tamanhos|size|sizes)[^<\n]{0,250}/gi) || [];
  const tamRegex = /\b(P|M|G|GG)\b/gi;
  contextosTamanho.forEach(contexto => {
    let tamMatch;
    while ((tamMatch = tamRegex.exec(contexto)) !== null) {
      tamanhosRoupa.push(tamMatch[1].toUpperCase());
    }
    tamRegex.lastIndex = 0;
  });
  if (tamanhosRoupa.length > 2) {
    variacoes.tamanhos = Array.from(new Set(tamanhosRoupa));
    if (tipoDetectado === 'generico') tipoDetectado = 'roupa';
  }

  // --- Cores ---
  const coresComuns = ['preto', 'branco', 'azul', 'vermelho', 'verde', 'amarelo', 'rosa', 'roxo', 'laranja', 'cinza', 'prata', 'dourado', 'bege', 'marrom'];
  const coresEncontradas: string[] = [];
  coresComuns.forEach(cor => {
    const regex = new RegExp('\\b' + cor + '\\b', 'i');
    if (regex.test(text)) {
      coresEncontradas.push(cor.charAt(0).toUpperCase() + cor.slice(1));
    }
  });
  if (coresEncontradas.length > 0) {
    variacoes.cores = coresEncontradas;
    if (tipoDetectado === 'generico') tipoDetectado = 'roupa';
  }

  return { variacoes, tipoDetectado };
}

export function extractFromHtml(html: string): ExtractedProduct {
  const doc = new DOMParser().parseFromString(html, "text/html");

  // 1. Título e Preço
  const title = (doc.querySelector('.ui-pdp-title')?.textContent || doc.querySelector('h1')?.textContent || '').trim();
  let price = (doc.querySelector('.andes-money-amount__fraction')?.textContent || '').trim();
  if (!price) {
    const match = html.match(/R\$\s*[0-9.,]+/);
    if (match) {
      price = match[0].replace('R$', '').trim().replace('.', '');
    }
  }

  // 2. Imagens
  const galleryImgs = Array.from(doc.querySelectorAll('.ui-pdp-gallery__figure__image, .ui-pdp-gallery__thumbnail__img, .ui-pdp-gallery__figure img, img[data-zoom]'));
  const srcs = Array.from(new Set(galleryImgs.map(i => (i as HTMLImageElement).src || i.getAttribute('data-src') || i.getAttribute('data-zoom')).filter(s => s && !s.includes('pixel') && s.startsWith('http'))));
  
  // Fallback for images if none found by ML selectors
  if (srcs.length === 0) {
    Array.from(doc.images).forEach(image => {
      if (image.src && image.src.startsWith('http')) {
        srcs.push(image.src);
      }
    });
  }
  
  const imageUrls = Array.from(new Set(srcs)).slice(0, 8) as string[];

  // 3. Características
  let charText = '';
  doc.querySelectorAll('.ui-pdp-features__list-item, .ui-vpp-striped-specs__row').forEach(p => {
    const text = p.textContent?.trim() || '';
    if (text.length > 1) {
      const label = p.querySelector('.ui-vpp-striped-specs__row__label')?.textContent?.trim();
      const value = p.querySelector('.ui-vpp-striped-specs__row__value')?.textContent?.trim();
      if (label && value) {
        charText += label + ": " + value + "\n";
      } else {
        charText += text + "\n";
      }
    }
  });

  // 4. Descrição
  const desc = (doc.querySelector('.ui-pdp-description__content')?.textContent || doc.querySelector('meta[name="description"]')?.getAttribute('content') || '').trim();

  // 5. Avaliações
  const reviews: any[] = [];
  const fakeNames = ["Cláudia Martins", "João Silva", "Maria Oliveira", "Ricardo Santos", "Fernanda Lima", "Marcos Pereira", "Juliana Costa", "André Souza"];
  
  const commentNodes = Array.from(doc.querySelectorAll('.ui-review-capability-comments__comment'));
  if (commentNodes.length > 0) {
    commentNodes.slice(0, 5).forEach((rev, idx) => {
      const text = rev.querySelector('.ui-review-capability-comments__comment__content')?.textContent?.trim() || '';
      const pics = Array.from(rev.querySelectorAll('img')).map(i => i.src || i.dataset.src).filter(s => s && s.startsWith('http'));
      
      reviews.push({
        nome: fakeNames[idx] || "Cliente",
        data: new Date().toLocaleDateString('pt-BR'),
        estrelas: 5,
        titulo: "Excelente produto",
        texto: text,
        fotos: pics.slice(0, 3)
      });
    });
  } else {
    // Generate 5 fake reviews if no comments are found but there is a product
    for (let i=0; i<5; i++) {
        reviews.push({
            nome: fakeNames[i % fakeNames.length],
            data: new Date().toLocaleDateString('pt-BR'),
            estrelas: 5,
            titulo: "Excelente produto",
            texto: "Muito bom, chegou certinho e bem embalado. Recomendo a loja!",
            fotos: []
        });
    }
  }

  // 6. Extrair variações automaticamente
  const { variacoes } = extractVariations(doc.body.textContent || '');

  return {
    title,
    price,
    imageUrls,
    characteristics: charText.trim(),
    description: desc,
    variations: Object.keys(variacoes).length > 0 ? JSON.stringify(variacoes, null, 2) : '',
    reviews: reviews.length > 0 ? JSON.stringify(reviews, null, 2) : ''
  };
}
