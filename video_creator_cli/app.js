/* ═══════════════════════════════════════════════════════
   CriativoMaker AI – app.js  (v2 – corrigido)
   Client-side video creation with AI-generated images
   Uses Pollinations.ai (free, no API key) for image generation
   Canvas API for rendering, MediaRecorder for export
   ═══════════════════════════════════════════════════════ */

/* ──────────────── STATE ──────────────── */
var appState = {
  aiSlides: [],
  images: [],
  textOverlays: [],
  nextImageId: 0,
  nextTextId: 0,
  isPlaying: false,
  isExporting: false,
  previewRAF: null,
  previewStartTime: null,
  currentTab: "ai-tab",
};

/* ──────────────── DOM HELPERS ──────────────── */
function $(sel) { return document.querySelector(sel); }
function $$(sel) { return document.querySelectorAll(sel); }

/* ──────────────── TOAST ──────────────── */
function toast(message, type) {
  type = type || "info";
  var el = document.createElement("div");
  el.className = "toast " + type;
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(function () { el.remove(); }, 4200);
}

/* ──────────────── TAB SWITCHING ──────────────── */
function switchTab(tabId) {
  appState.currentTab = tabId;
  $$(".nav-tab").forEach(function (t) {
    t.classList.toggle("active", t.getAttribute("data-tab") === tabId);
  });
  $$(".tab-content").forEach(function (s) {
    s.classList.toggle("active", s.getAttribute("data-tab") === tabId);
  });
}

/* ──────────────── SETTINGS ──────────────── */
function getSettings() {
  var resEl;
  if (appState.currentTab === "ai-tab") {
    resEl = $("#ai-format");
  } else {
    resEl = $("#manual-resolution") || $("#resolution-select");
  }
  var resStr = resEl ? resEl.value : "1920x1080";
  var parts = resStr.split("x");
  var w = parseInt(parts[0], 10);
  var h = parseInt(parts[1], 10);

  return {
    slideDuration: parseFloat($("#slide-duration").value) || 3,
    transition: $("#transition-select").value || "fade",
    transitionDuration: parseFloat($("#transition-duration").value) || 0.5,
    width: w,
    height: h,
    fps: parseInt($("#fps-select").value, 10) || 30,
    bgColor: ($("#bg-color") ? $("#bg-color").value : "#000000"),
    fontFamily: ($("#font-family") ? $("#font-family").value : "Inter"),
    fontSize: ($("#font-size") ? parseInt($("#font-size").value, 10) : 48),
    textColor: ($("#text-color") ? $("#text-color").value : "#ffffff"),
    strokeColor: ($("#text-stroke-color") ? $("#text-stroke-color").value : "#000000"),
    strokeWidth: ($("#text-stroke-width") ? parseInt($("#text-stroke-width").value, 10) : 2),
    bold: ($("#text-bold") ? $("#text-bold").checked : false),
    italic: ($("#text-italic") ? $("#text-italic").checked : false),
    shadow: ($("#text-shadow-toggle") ? $("#text-shadow-toggle").checked : true),
  };
}

function getCurrentSlides() {
  if (appState.currentTab === "ai-tab") {
    return appState.aiSlides.filter(function (s) { return s.loaded && s.img; });
  }
  return appState.images;
}

function getCurrentTexts() {
  if (appState.currentTab === "ai-tab") {
    var loaded = appState.aiSlides.filter(function (s) { return s.loaded; });
    return loaded.map(function (s, i) {
      return {
        id: s.id, text: s.text, slideIndex: i, position: s.textPosition || "bottom",
        fontFamily: "Inter", fontSize: 44, color: "#ffffff", strokeColor: "#000000",
        strokeWidth: 3, bold: true, italic: false, shadow: true,
      };
    });
  }
  return appState.textOverlays;
}

function totalDuration(slides) {
  var s = getSettings();
  var n = slides.length;
  if (n === 0) return 0;
  return n * s.slideDuration + Math.max(0, n - 1) * s.transitionDuration;
}

/* ══════════════════════════════════════════
   AI COMMERCIAL GENERATION
   Uses Pollinations.ai – free, no API key
   ══════════════════════════════════════════ */

// Scene templates per style
var SCENE_TEMPLATES = {
  modern: {
    scenes: [
      "elegant minimalist product showcase, clean white background, studio lighting, commercial photography",
      "modern lifestyle scene, clean aesthetic, soft natural light, product in context, advertising photo",
      "close-up detail shot, premium quality, macro photography, soft bokeh background",
      "happy customer using product, modern interior, natural smile, lifestyle ad",
      "brand logo on minimal background, clean typography, professional design",
      "product flatlay arrangement, modern accessories, top view, styled photo",
      "dynamic product in motion, sleek environment, professional commercial",
      "product on display, elegant showcase, modern retail, premium"
    ],
    texts: [
      "Descubra o novo {product}",
      "Design que inspira",
      "Qualidade Premium",
      "Feito para você",
      "A diferença está nos detalhes",
      "Experimente agora",
      "O futuro é agora",
      "{cta}"
    ]
  },
  luxury: {
    scenes: [
      "luxury product on black velvet, golden accents, dramatic studio lighting, premium",
      "premium lifestyle, luxury interior, marble and gold, product elegantly placed",
      "close-up with sparkle, ultra premium feel, dark background, luxury",
      "exclusive VIP experience, champagne tones, sophisticated atmosphere",
      "golden hour light on product, luxury setting, cinematic photography",
      "product on marble surface, rose gold accents, premium aesthetic",
      "elegant hands holding product, sophisticated styling, luxury brand",
      "brand emblem gold foil on dark background, exclusive luxury design"
    ],
    texts: [
      "Exclusividade {product}",
      "Luxo Redefinido",
      "Para quem merece o melhor",
      "Elegância Atemporal",
      "Coleção Premium",
      "Experiência Única",
      "Arte e Sofisticação",
      "{cta}"
    ]
  },
  vibrant: {
    scenes: [
      "colorful product explosion, vibrant neon colors, dynamic composition, pop art",
      "energetic young people, festival vibes, colorful confetti, product featured",
      "product surrounded by tropical fruits and flowers, vibrant colors, fresh",
      "bold geometric background, bright gradients, product center stage",
      "street art wall, urban energy, colorful graffiti, product displayed",
      "neon lights city night, cyberpunk vibes, product glowing, vivid",
      "paint splash and color explosion, creative artistic, product reveal",
      "rainbow gradient, bold typography, energetic colorful design"
    ],
    texts: [
      "{product} chegou!",
      "Cores que inspiram",
      "Energia pura!",
      "Destaque-se!",
      "Viva com intensidade",
      "A revolução das cores",
      "Seu estilo, sua regra",
      "{cta}"
    ]
  },
  nature: {
    scenes: [
      "organic product in lush green forest, natural sunlight filtering through leaves",
      "product on wooden table outdoors, garden background, fresh morning dew",
      "natural ingredients, farm to table, organic and fresh, green leaves",
      "sustainable packaging, eco friendly, green background, nature",
      "person enjoying nature wellness, product in natural setting, peaceful",
      "water droplets on fresh product, macro, pure and clean, nature",
      "sunrise over green landscape, peaceful, product silhouette, organic",
      "earth tones, natural textures, eco brand, organic feel"
    ],
    texts: [
      "100% Natural {product}",
      "Da natureza para você",
      "Pureza genuína",
      "Sustentável e Consciente",
      "Ingredientes reais",
      "Respeito à natureza",
      "Viva naturalmente",
      "{cta}"
    ]
  },
  tech: {
    scenes: [
      "futuristic product display, holographic interface, blue neon lights, sci-fi tech",
      "tech workspace, multiple screens, product integration, modern office, technology",
      "circuit board close-up transitioning to product, technology meets design",
      "AI visualization, neural network, data streams, product at center, tech",
      "smart device in use, connected home, seamless technology, modern",
      "product blueprint, technical drawing, engineering precision, tech design",
      "digital particles forming product shape, high-tech futuristic, dark",
      "minimal tech logo, dark background, glowing blue accents, future"
    ],
    texts: [
      "{product} — Tecnologia de Ponta",
      "Inovação que transforma",
      "O futuro na sua mão",
      "Inteligência em cada detalhe",
      "Performance máxima",
      "Conectado ao amanhã",
      "Smart. Rápido. Poderoso.",
      "{cta}"
    ]
  },
  food: {
    scenes: [
      "delicious food product, steam rising, warm lighting, gourmet presentation",
      "chef preparing dish with product, professional kitchen, culinary art, food",
      "ingredient close-up, fresh and appetizing, water splash, macro food",
      "family dinner table, warm atmosphere, product served beautifully, food",
      "food flatlay, artistic arrangement, rustic wood background, delicious",
      "pouring sauce or drink, appetizing food photography, professional",
      "market fresh ingredients, colorful produce, farm fresh, organic food",
      "restaurant brand, cozy ambiance, food logo, warm tones"
    ],
    texts: [
      "Sabor {product}",
      "Irresistível!",
      "Feito com amor",
      "Ingredientes selecionados",
      "Momento delicioso",
      "Receita de família",
      "Experimente esta delícia",
      "{cta}"
    ]
  },
  fashion: {
    scenes: [
      "fashion model wearing product, editorial shoot, dramatic lighting, vogue",
      "clothing rack, curated collection, boutique atmosphere, styled fashion",
      "fashion detail shot, texture and fabric close-up, premium material",
      "street fashion, urban setting, confident model, trendy style",
      "fashion show runway, dramatic lighting, product showcase, haute couture",
      "accessories flatlay, styled composition, magazine quality, fashion",
      "couple in stylish outfits, romantic setting, lifestyle fashion",
      "fashion brand logo, elegant typography, lookbook style"
    ],
    texts: [
      "Nova Coleção {product}",
      "Estilo sem limites",
      "Vista-se de atitude",
      "Tendência da temporada",
      "Elegância casual",
      "Seu look perfeito",
      "Moda que transforma",
      "{cta}"
    ]
  },
  fitness: {
    scenes: [
      "athletic person using product, gym setting, dynamic action, powerful fitness",
      "fitness equipment and product, motivation, energetic atmosphere, gym",
      "healthy smoothie or supplement, fresh fruits, energy, vitality, fitness",
      "outdoor workout, sunrise, determination, product in use, athletic",
      "fitness transformation, inspiring results, before after, motivation",
      "protein or supplement details, scientific, nutrition, fitness product",
      "group fitness class, energy, community, motivation, sport",
      "fitness brand logo, bold and powerful, athletic design"
    ],
    texts: [
      "{product} Power",
      "Supere seus limites",
      "Energia total",
      "Resultados reais",
      "Corpo e Mente",
      "Transformação começa aqui",
      "Sem desculpas!",
      "{cta}"
    ]
  },
  corporate: {
    scenes: [
      "professional business product, modern office, clean desk, corporate style",
      "team meeting using product, conference room, professional collaboration",
      "business analytics dashboard, growth charts, success metrics, corporate",
      "handshake deal, partnership, trust, professional business relationship",
      "modern office building, corporate headquarters, impressive architecture",
      "professional presentation, keynote, product demonstration, business",
      "global business, world map, international reach, connected, corporate",
      "corporate brand logo, professional blue tones, trust, business"
    ],
    texts: [
      "{product} — Soluções Empresariais",
      "Confiança que gera resultados",
      "Parceiro do seu negócio",
      "Crescimento sustentável",
      "Eficiência comprovada",
      "Líder de mercado",
      "Seu sucesso, nossa missão",
      "{cta}"
    ]
  },
  playful: {
    scenes: [
      "fun colorful product display, confetti, balloons, celebration, playful happy",
      "happy family playing, product in scene, joy and laughter, fun colorful",
      "cartoon colorful background, fun patterns, product center, playful bright",
      "surprise unboxing, excitement, gift wrapping, product reveal, fun",
      "playful mascot with product, cute illustration style, colorful fun",
      "bubble gum colors, candy shop aesthetic, sweet and fun, playful",
      "playground or park, outdoor fun, product adventure, happy kids",
      "fun brand logo, bouncy letters, colorful youthful energy, playful"
    ],
    texts: [
      "{product} é diversão!",
      "Feito para sorrir",
      "Alegria garantida!",
      "Para toda a família",
      "Diversão sem parar",
      "Momentos mágicos",
      "Venha brincar!",
      "{cta}"
    ]
  }
};

/* ── Pollinations.ai image URL builder ── */
function getAIImageUrl(prompt, width, height, seed) {
  var encoded = encodeURIComponent(prompt);
  return "https://image.pollinations.ai/prompt/" + encoded + "?width=" + width + "&height=" + height + "&seed=" + seed + "&nologo=true";
}

/* ── Image loader with timeout (NO crossOrigin for file:// compatibility) ── */
function loadImage(url, timeoutMs) {
  timeoutMs = timeoutMs || 60000; // 60 second timeout
  return new Promise(function (resolve, reject) {
    var img = new Image();
    var timer = setTimeout(function () {
      img.onload = null;
      img.onerror = null;
      reject(new Error("Timeout loading image"));
    }, timeoutMs);

    img.onload = function () {
      clearTimeout(timer);
      resolve(img);
    };
    img.onerror = function () {
      clearTimeout(timer);
      reject(new Error("Failed to load image"));
    };
    // Do NOT set crossOrigin here – it breaks on file:// protocol
    img.src = url;
  });
}

/* ══════════════════════════════════════════
   MAIN AI GENERATION FUNCTION
   ══════════════════════════════════════════ */
function generateAICommercial() {
  try {
    var product = ($("#ai-product").value || "").trim();
    var description = ($("#ai-description").value || "").trim();
    var style = ($("#ai-style").value || "modern");
    var audience = ($("#ai-audience").value || "").trim();
    var numSlides = parseInt($("#ai-slides").value, 10) || 5;
    var cta = ($("#ai-cta").value || "").trim() || "Saiba Mais!";
    var formatStr = ($("#ai-format").value || "1920x1080");
    var parts = formatStr.split("x");
    var imgW = parseInt(parts[0], 10);
    var imgH = parseInt(parts[1], 10);

    if (!product) {
      toast("Preencha o nome do produto!", "error");
      return;
    }

    // Disable button immediately
    var btnGen = $("#btn-generate-ai");
    btnGen.disabled = true;
    btnGen.innerHTML = '<div class="ai-status-spinner" style="width:20px;height:20px;display:inline-block;vertical-align:middle;margin-right:8px;border-width:2px;"></div> Gerando...';

    // Show status
    var statusEl = $("#ai-status");
    var statusText = $("#ai-status-text");
    var aiProgressFill = $("#ai-progress-fill");
    statusEl.classList.remove("hidden");
    statusText.textContent = "Preparando cenas do comercial...";
    aiProgressFill.style.width = "5%";

    // Get template
    var template = SCENE_TEMPLATES[style] || SCENE_TEMPLATES.modern;

    // Build slides
    var productContext = product + (description ? ", " + description : "");
    var audienceHint = audience ? ", targeting " + audience : "";
    var slides = [];

    for (var i = 0; i < numSlides; i++) {
      var sceneIdx = i % template.scenes.length;
      var textIdx = i % template.texts.length;
      var isLastSlide = (i === numSlides - 1);

      var scenePrompt = template.scenes[sceneIdx] + ", featuring " + productContext + audienceHint + ", high quality, 8k, professional advertising";

      var textContent = template.texts[isLastSlide ? template.texts.length - 1 : textIdx];
      textContent = textContent.replace("{product}", product).replace("{cta}", cta);

      var seed = Math.floor(Math.random() * 999999);
      var genW = Math.min(imgW, 1024);
      var genH = Math.min(imgH, 1024);

      slides.push({
        id: i,
        prompt: scenePrompt,
        imageUrl: getAIImageUrl(scenePrompt, genW, genH, seed),
        img: null,
        text: textContent,
        textPosition: isLastSlide ? "center" : "bottom",
        loaded: false,
        seed: seed,
      });
    }

    appState.aiSlides = slides;
    renderAISlideCards();

    // Load images one by one
    loadSlidesSequentially(slides, 0, statusText, aiProgressFill, statusEl, btnGen);

  } catch (err) {
    toast("Erro: " + err.message, "error");
    console.error("generateAICommercial error:", err);
    var btnGen2 = $("#btn-generate-ai");
    if (btnGen2) {
      btnGen2.disabled = false;
      btnGen2.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span>Gerar Comercial com IA</span>';
    }
  }
}

function loadSlidesSequentially(slides, index, statusText, aiProgressFill, statusEl, btnGen) {
  if (index >= slides.length) {
    // All done!
    aiProgressFill.style.width = "100%";
    statusText.textContent = "✅ Comercial gerado com sucesso!";
    setTimeout(function () { statusEl.classList.add("hidden"); }, 2500);
    btnGen.disabled = false;
    btnGen.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span>Gerar Comercial com IA</span>';
    var btnExport = $("#btn-export");
    if (btnExport) btnExport.disabled = false;
    var emptyMsg = $("#empty-canvas-msg");
    if (emptyMsg) emptyMsg.style.display = "none";
    renderTimeline();
    drawAIFrame(0);
    toast("Comercial gerado! Edite os textos e exporte.", "success");
    return;
  }

  var slide = slides[index];
  var pct = Math.round(((index) / slides.length) * 90) + 5;
  aiProgressFill.style.width = pct + "%";
  statusText.textContent = "🎨 Gerando imagem " + (index + 1) + " de " + slides.length + " com IA...";

  loadImage(slide.imageUrl, 90000)
    .then(function (img) {
      slide.img = img;
      slide.loaded = true;
      renderAISlideCards();
      drawAIFrame(0);
      // Next slide
      loadSlidesSequentially(slides, index + 1, statusText, aiProgressFill, statusEl, btnGen);
    })
    .catch(function (err) {
      console.warn("Slide " + (index + 1) + " failed, retrying...", err);
      // Retry with different seed
      slide.seed = Math.floor(Math.random() * 999999);
      var formatStr = ($("#ai-format").value || "1920x1080");
      var parts = formatStr.split("x");
      var genW = Math.min(parseInt(parts[0], 10), 1024);
      var genH = Math.min(parseInt(parts[1], 10), 1024);
      slide.imageUrl = getAIImageUrl(slide.prompt, genW, genH, slide.seed);

      loadImage(slide.imageUrl, 90000)
        .then(function (img) {
          slide.img = img;
          slide.loaded = true;
          renderAISlideCards();
          drawAIFrame(0);
          loadSlidesSequentially(slides, index + 1, statusText, aiProgressFill, statusEl, btnGen);
        })
        .catch(function (err2) {
          console.error("Slide " + (index + 1) + " failed permanently:", err2);
          toast("Imagem " + (index + 1) + " falhou. Continuando...", "error");
          slide.loaded = true; // Mark as loaded to avoid blocking
          loadSlidesSequentially(slides, index + 1, statusText, aiProgressFill, statusEl, btnGen);
        });
    });
}

/* ── Regenerate a single slide ── */
function regenerateSlide(slideId) {
  var slide = null;
  for (var i = 0; i < appState.aiSlides.length; i++) {
    if (appState.aiSlides[i].id === slideId) { slide = appState.aiSlides[i]; break; }
  }
  if (!slide) return;

  slide.loaded = false;
  slide.seed = Math.floor(Math.random() * 999999);
  var formatStr = ($("#ai-format").value || "1920x1080");
  var parts = formatStr.split("x");
  var genW = Math.min(parseInt(parts[0], 10), 1024);
  var genH = Math.min(parseInt(parts[1], 10), 1024);
  slide.imageUrl = getAIImageUrl(slide.prompt, genW, genH, slide.seed);
  renderAISlideCards();
  toast("Regenerando imagem...", "info");

  loadImage(slide.imageUrl, 90000)
    .then(function (img) {
      slide.img = img;
      slide.loaded = true;
      renderAISlideCards();
      drawAIFrame(0);
      toast("Imagem regenerada!", "success");
    })
    .catch(function () {
      toast("Erro ao regenerar. Tente novamente.", "error");
      slide.loaded = true;
      renderAISlideCards();
    });
}

function removeAISlide(id) {
  appState.aiSlides = appState.aiSlides.filter(function (s) { return s.id !== id; });
  renderAISlideCards();
  renderTimeline();
  drawAIFrame(0);
  if (appState.aiSlides.length === 0) {
    var emptyMsg = $("#empty-canvas-msg");
    if (emptyMsg) emptyMsg.style.display = "flex";
    var btnExport = $("#btn-export");
    if (btnExport) btnExport.disabled = true;
  }
}

/* ──────────────── RENDER AI SLIDE CARDS ──────────────── */
function renderAISlideCards() {
  var list = $("#ai-slides-list");
  if (!list) return;
  list.innerHTML = "";

  if (appState.aiSlides.length === 0) {
    list.innerHTML = '<div class="ai-slides-empty"><p>Os slides aparecerão aqui após a geração</p></div>';
    return;
  }

  appState.aiSlides.forEach(function (slide, idx) {
    var card = document.createElement("div");
    card.className = "ai-slide-card";

    var imgHtml;
    if (slide.loaded && slide.img) {
      imgHtml = '<img class="ai-slide-card-img" src="' + slide.imageUrl + '" alt="Slide ' + (idx + 1) + '">';
    } else {
      imgHtml = '<div class="ai-slide-card-img" style="display:flex;align-items:center;justify-content:center;background:var(--bg-secondary);"><div class="ai-status-spinner"></div></div>';
    }

    var isLast = (idx === appState.aiSlides.length - 1);
    card.innerHTML =
      '<div class="ai-slide-card-header">' +
        '<span>Slide ' + (idx + 1) + (isLast ? ' (CTA)' : '') + '</span>' +
        '<div style="display:flex;gap:.3rem;">' +
          '<button class="btn-regen" onclick="regenerateSlide(' + slide.id + ')" title="Regenerar">🔄 Nova IA</button>' +
          '<button class="btn-remove" onclick="removeAISlide(' + slide.id + ')" title="Remover">✕</button>' +
        '</div>' +
      '</div>' +
      imgHtml +
      '<textarea data-slide-id="' + slide.id + '">' + slide.text + '</textarea>' +
      '<div class="ai-slide-card-actions">' +
        '<select class="select-styled select-sm" data-slide-id="' + slide.id + '">' +
          '<option value="top"' + (slide.textPosition === "top" ? " selected" : "") + '>Topo</option>' +
          '<option value="center"' + (slide.textPosition === "center" ? " selected" : "") + '>Centro</option>' +
          '<option value="bottom"' + (slide.textPosition === "bottom" ? " selected" : "") + '>Inferior</option>' +
        '</select>' +
      '</div>';

    // Events
    var ta = card.querySelector("textarea");
    ta.addEventListener("input", function (e) {
      slide.text = e.target.value;
      drawAIFrame(0);
    });
    var posSelect = card.querySelector("select");
    posSelect.addEventListener("change", function (e) {
      slide.textPosition = e.target.value;
      drawAIFrame(0);
    });

    list.appendChild(card);
  });
}

/* ══════════════════════════════════════════
   CANVAS DRAWING
   ══════════════════════════════════════════ */

function drawImageCover(targetCtx, imgEl, cw, ch, ox, oy, sc) {
  ox = ox || 0; oy = oy || 0; sc = sc || 1;
  var iw = imgEl.naturalWidth || imgEl.width;
  var ih = imgEl.naturalHeight || imgEl.height;
  if (iw === 0 || ih === 0) return;
  var imgRatio = iw / ih;
  var canvasRatio = cw / ch;
  var sw, sh, sx, sy;
  if (imgRatio > canvasRatio) { sh = ih; sw = ih * canvasRatio; sx = (iw - sw) / 2; sy = 0; }
  else { sw = iw; sh = iw / canvasRatio; sx = 0; sy = (ih - sh) / 2; }
  try {
    targetCtx.drawImage(imgEl, sx, sy, sw, sh, ox, oy, cw * sc, ch * sc);
  } catch (e) {
    // Tainted canvas fallback – just fill with a color
    targetCtx.fillStyle = "#1a1a3e";
    targetCtx.fillRect(ox, oy, cw * sc, ch * sc);
  }
}

function wrapTextOnCtx(context, text, x, y, maxWidth, lineHeight, stroke) {
  var lines = text.split("\n");
  var rendered = [];
  lines.forEach(function (line) {
    var words = line.split(" ");
    var current = "";
    words.forEach(function (word) {
      var test = current ? current + " " + word : word;
      if (context.measureText(test).width > maxWidth && current) {
        rendered.push(current);
        current = word;
      } else {
        current = test;
      }
    });
    if (current) rendered.push(current);
  });
  var startY = y - ((rendered.length - 1) * lineHeight) / 2;
  rendered.forEach(function (line, i) {
    if (stroke) context.strokeText(line, x, startY + i * lineHeight);
    else context.fillText(line, x, startY + i * lineHeight);
  });
}

function drawTextsOnCtx(targetCtx, texts, slideIndex, cw, ch) {
  var overlays = texts.filter(function (t) { return t.slideIndex === -1 || t.slideIndex === slideIndex; });
  overlays.forEach(function (ov) {
    var family = ov.fontFamily || "Inter";
    var size = ov.fontSize || 44;
    var weight = ov.bold ? "bold" : "normal";
    var fontStyle = ov.italic ? "italic" : "normal";
    targetCtx.font = fontStyle + " " + weight + " " + size + "px '" + family + "', sans-serif";
    targetCtx.textAlign = "center";

    var x = cw / 2;
    var y;
    if (ov.position === "top") y = size + 40;
    else if (ov.position === "bottom") y = ch - 50;
    else y = ch / 2;

    if (ov.shadow) {
      targetCtx.shadowColor = "rgba(0,0,0,0.8)";
      targetCtx.shadowBlur = 15;
      targetCtx.shadowOffsetX = 3;
      targetCtx.shadowOffsetY = 3;
    }

    var sw2 = ov.strokeWidth || 2;
    if (sw2 > 0) {
      targetCtx.strokeStyle = ov.strokeColor || "#000000";
      targetCtx.lineWidth = sw2 * 2;
      targetCtx.lineJoin = "round";
      wrapTextOnCtx(targetCtx, ov.text, x, y, cw * 0.85, size * 1.3, true);
    }

    targetCtx.fillStyle = ov.color || "#ffffff";
    wrapTextOnCtx(targetCtx, ov.text, x, y, cw * 0.85, size * 1.3, false);

    targetCtx.shadowColor = "transparent";
    targetCtx.shadowBlur = 0;
    targetCtx.shadowOffsetX = 0;
    targetCtx.shadowOffsetY = 0;
  });
}

function applyTransition(targetCtx, imgA, imgB, progress, cw, ch, type) {
  switch (type) {
    case "fade":
      drawImageCover(targetCtx, imgA, cw, ch);
      targetCtx.globalAlpha = progress;
      drawImageCover(targetCtx, imgB, cw, ch);
      targetCtx.globalAlpha = 1;
      break;
    case "slide-left":
      drawImageCover(targetCtx, imgA, cw, ch, -progress * cw);
      drawImageCover(targetCtx, imgB, cw, ch, cw - progress * cw);
      break;
    case "slide-right":
      drawImageCover(targetCtx, imgA, cw, ch, progress * cw);
      drawImageCover(targetCtx, imgB, cw, ch, -cw + progress * cw);
      break;
    case "zoom-in":
      var sA = 1 + progress * 0.3;
      targetCtx.save();
      targetCtx.globalAlpha = 1 - progress;
      targetCtx.translate(cw / 2, ch / 2);
      targetCtx.scale(sA, sA);
      targetCtx.translate(-cw / 2, -ch / 2);
      drawImageCover(targetCtx, imgA, cw, ch);
      targetCtx.restore();
      targetCtx.globalAlpha = progress;
      drawImageCover(targetCtx, imgB, cw, ch);
      targetCtx.globalAlpha = 1;
      break;
    case "zoom-out":
      var sB = 1 + (1 - progress) * 0.3;
      drawImageCover(targetCtx, imgA, cw, ch);
      targetCtx.save();
      targetCtx.globalAlpha = progress;
      targetCtx.translate(cw / 2, ch / 2);
      targetCtx.scale(sB, sB);
      targetCtx.translate(-cw / 2, -ch / 2);
      drawImageCover(targetCtx, imgB, cw, ch);
      targetCtx.restore();
      targetCtx.globalAlpha = 1;
      break;
    default:
      if (progress < 0.5) drawImageCover(targetCtx, imgA, cw, ch);
      else drawImageCover(targetCtx, imgB, cw, ch);
      break;
  }
}

function drawFrameGeneric(targetCtx, targetCanvas, time, slides, texts, s) {
  var cw = targetCanvas.width;
  var ch = targetCanvas.height;
  targetCtx.fillStyle = s.bgColor || "#000000";
  targetCtx.fillRect(0, 0, cw, ch);

  if (slides.length === 0) return;

  var slideDur = s.slideDuration;
  var transDur = s.transitionDuration;
  var total = totalDuration(slides);
  var clampedTime = Math.min(time, total);

  var elapsed = 0;
  var slideIdx = 0;
  for (var i = 0; i < slides.length; i++) {
    var thisDur = slideDur + (i < slides.length - 1 ? transDur : 0);
    if (elapsed + thisDur > clampedTime) { slideIdx = i; break; }
    elapsed += thisDur;
    slideIdx = i;
  }
  var localTime = clampedTime - elapsed;
  var inTransition = localTime > slideDur && slideIdx < slides.length - 1;
  var transProgress = inTransition ? (localTime - slideDur) / transDur : 0;

  var currentImg = slides[slideIdx].img;
  if (!currentImg) return;

  if (inTransition && slideIdx + 1 < slides.length && slides[slideIdx + 1].img) {
    applyTransition(targetCtx, currentImg, slides[slideIdx + 1].img, transProgress, cw, ch, s.transition);
  } else {
    var zoomProgress = localTime / slideDur;
    var scale = 1 + 0.04 * zoomProgress;
    var ox = -(scale - 1) * cw / 2;
    var oy = -(scale - 1) * ch / 2;
    targetCtx.save();
    targetCtx.translate(ox, oy);
    drawImageCover(targetCtx, currentImg, cw, ch, 0, 0, scale);
    targetCtx.restore();
  }

  drawTextsOnCtx(targetCtx, texts, slideIdx, cw, ch);

  var playheadEl = $("#timeline-playhead");
  if (playheadEl && total > 0) playheadEl.style.left = (clampedTime / total) * 100 + "%";
}

function drawAIFrame(time) {
  var canvasEl = $("#preview-canvas");
  if (!canvasEl) return;
  var ctxLocal = canvasEl.getContext("2d");
  var s = getSettings();
  canvasEl.width = s.width;
  canvasEl.height = s.height;
  var slides = getCurrentSlides();
  var texts = getCurrentTexts();
  drawFrameGeneric(ctxLocal, canvasEl, time, slides, texts, s);
}

/* ──────────────── TIMELINE ──────────────── */
function renderTimeline() {
  var track = $("#timeline-track");
  if (!track) return;
  track.innerHTML = "";
  var s = getSettings();
  var slides = getCurrentSlides();
  var total = totalDuration(slides);
  slides.forEach(function (item, idx) {
    var seg = document.createElement("div");
    seg.className = "timeline-segment";
    var segDur = s.slideDuration + (idx < slides.length - 1 ? s.transitionDuration : 0);
    seg.style.flex = "" + (segDur / Math.max(total, 0.1));
    var imgSrc = item.url || item.imageUrl || "";
    seg.innerHTML = (imgSrc ? '<img src="' + imgSrc + '" alt="">' : '') + '<span>' + (idx + 1) + '</span>';
    track.appendChild(seg);
  });
}

/* ──────────────── PREVIEW PLAYBACK ──────────────── */
function startPreview() {
  var slides = getCurrentSlides();
  if (slides.length === 0) { toast("Nenhum slide para reproduzir!", "error"); return; }
  appState.isPlaying = true;
  appState.previewStartTime = performance.now();
  var btnPlayEl = $("#btn-play-preview");
  var btnStopEl = $("#btn-stop-preview");
  if (btnPlayEl) btnPlayEl.disabled = true;
  if (btnStopEl) btnStopEl.disabled = false;
  animatePreview();
}

function stopPreview() {
  appState.isPlaying = false;
  var btnPlayEl = $("#btn-play-preview");
  var btnStopEl = $("#btn-stop-preview");
  if (btnPlayEl) btnPlayEl.disabled = false;
  if (btnStopEl) btnStopEl.disabled = true;
  if (appState.previewRAF) cancelAnimationFrame(appState.previewRAF);
  drawAIFrame(0);
}

function animatePreview() {
  if (!appState.isPlaying) return;
  var elapsed = (performance.now() - appState.previewStartTime) / 1000;
  var slides = getCurrentSlides();
  var total = totalDuration(slides);
  if (elapsed >= total) { stopPreview(); return; }
  drawAIFrame(elapsed);
  appState.previewRAF = requestAnimationFrame(animatePreview);
}

/* ──────────────── VIDEO EXPORT ──────────────── */
function exportVideo() {
  var slides = getCurrentSlides();
  if (slides.length === 0 || appState.isExporting) return;
  appState.isExporting = true;
  var btnExportEl = $("#btn-export");
  if (btnExportEl) btnExportEl.disabled = true;
  var progressWrapEl = $("#export-progress");
  var progressFillEl = $("#progress-fill");
  var progressTextEl = $("#progress-text");
  if (progressWrapEl) progressWrapEl.classList.remove("hidden");

  var s = getSettings();
  var total = totalDuration(slides);
  var fps = s.fps;
  var totalFrames = Math.ceil(total * fps);
  var texts = getCurrentTexts();

  var offCanvas = document.createElement("canvas");
  offCanvas.width = s.width;
  offCanvas.height = s.height;
  var offCtx = offCanvas.getContext("2d");

  var mimeType = "video/webm;codecs=vp9";
  if (!MediaRecorder.isTypeSupported(mimeType)) {
    mimeType = "video/webm;codecs=vp8";
    if (!MediaRecorder.isTypeSupported(mimeType)) mimeType = "video/webm";
  }

  var stream = offCanvas.captureStream(0);
  var recorder = new MediaRecorder(stream, { mimeType: mimeType, videoBitsPerSecond: 8000000 });
  var chunks = [];
  recorder.ondataavailable = function (e) { if (e.data.size > 0) chunks.push(e.data); };

  var doneResolve;
  var donePromise = new Promise(function (resolve) { doneResolve = resolve; });
  recorder.onstop = function () { doneResolve(); };
  recorder.start();

  var frame = 0;
  function processFrame() {
    if (frame >= totalFrames) {
      recorder.stop();
      donePromise.then(function () {
        var blob = new Blob(chunks, { type: mimeType });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = "comercial_" + Date.now() + ".webm";
        a.click();
        URL.revokeObjectURL(url);

        if (progressFillEl) progressFillEl.style.width = "100%";
        if (progressTextEl) progressTextEl.textContent = "Concluído! ✅";
        toast("Vídeo comercial exportado com sucesso!", "success");

        setTimeout(function () {
          if (progressWrapEl) progressWrapEl.classList.add("hidden");
          if (progressFillEl) progressFillEl.style.width = "0%";
          if (progressTextEl) progressTextEl.textContent = "0%";
          appState.isExporting = false;
          if (btnExportEl) btnExportEl.disabled = getCurrentSlides().length === 0;
        }, 2500);
      });
      return;
    }

    var time = frame / fps;
    drawFrameGeneric(offCtx, offCanvas, time, slides, texts, s);

    var videoTrack = stream.getVideoTracks()[0];
    if (videoTrack && typeof videoTrack.requestFrame === "function") {
      videoTrack.requestFrame();
    }

    var pct = Math.round((frame / totalFrames) * 100);
    if (progressFillEl) progressFillEl.style.width = pct + "%";
    if (progressTextEl) progressTextEl.textContent = pct + "% – frame " + (frame + 1) + " / " + totalFrames;

    frame++;
    if (frame % 3 === 0) {
      setTimeout(processFrame, 0);
    } else {
      processFrame();
    }
  }

  processFrame();
}

/* ══════════════════════════════════════════
   MANUAL MODE
   ══════════════════════════════════════════ */
function addImages(files) {
  for (var i = 0; i < files.length; i++) {
    if (appState.images.length >= 20) break;
    if (!files[i].type.startsWith("image/")) continue;
    var id = appState.nextImageId++;
    var url = URL.createObjectURL(files[i]);
    var img = new Image();
    img.src = url;
    img.onload = function () { renderManualUI(); };
    appState.images.push({ id: id, file: files[i], url: url, img: img });
  }
  renderManualUI();
}

function removeImage(id) {
  for (var i = 0; i < appState.images.length; i++) {
    if (appState.images[i].id === id) {
      URL.revokeObjectURL(appState.images[i].url);
      appState.images.splice(i, 1);
      break;
    }
  }
  renderManualUI();
}

function addTextOverlay() {
  appState.textOverlays.push({
    id: appState.nextTextId++, text: "Seu texto aqui", slideIndex: -1, position: "center",
    fontFamily: "Inter", fontSize: 48, color: "#ffffff", strokeColor: "#000000",
    strokeWidth: 2, bold: false, italic: false, shadow: true,
  });
  renderManualUI();
}

function removeTextOverlay(id) {
  appState.textOverlays = appState.textOverlays.filter(function (t) { return t.id !== id; });
  renderManualUI();
}

function renderManualUI() {
  var imgCountEl = $("#img-count");
  var manualEmpty = $("#manual-empty-msg");
  var btnExportManual = $("#btn-export-manual");
  var imageListEl = $("#image-list");

  if (imgCountEl) imgCountEl.textContent = appState.images.length;
  if (manualEmpty) manualEmpty.style.display = appState.images.length > 0 ? "none" : "flex";
  if (btnExportManual) btnExportManual.disabled = appState.images.length === 0;

  if (imageListEl) {
    imageListEl.innerHTML = "";
    appState.images.forEach(function (item, idx) {
      var div = document.createElement("div");
      div.className = "image-item";
      div.innerHTML = '<span class="image-item-handle">☰</span><img src="' + item.url + '" alt=""><span class="image-item-name">' + item.file.name + '</span><button class="btn-remove" title="Remover">✕</button>';
      div.querySelector(".btn-remove").addEventListener("click", function () { removeImage(item.id); });
      imageListEl.appendChild(div);
    });
  }
}

/* ══════════════════════════════════════════
   EVENT LISTENERS (runs on DOM ready)
   ══════════════════════════════════════════ */
document.addEventListener("DOMContentLoaded", function () {
  // AI slides range
  var aiSlidesRange = $("#ai-slides");
  if (aiSlidesRange) {
    aiSlidesRange.addEventListener("input", function (e) {
      var val = $("#ai-slides-val");
      if (val) val.textContent = e.target.value;
    });
  }

  // Settings ranges
  var slideDurRange = $("#slide-duration");
  if (slideDurRange) {
    slideDurRange.addEventListener("input", function (e) {
      var val = $("#slide-duration-val");
      if (val) val.textContent = e.target.value + "s";
      renderTimeline();
      drawAIFrame(0);
    });
  }
  var transDurRange = $("#transition-duration");
  if (transDurRange) {
    transDurRange.addEventListener("input", function (e) {
      var val = $("#transition-duration-val");
      if (val) val.textContent = e.target.value + "s";
      renderTimeline();
      drawAIFrame(0);
    });
  }

  // Color
  var bgColorInput = $("#bg-color");
  if (bgColorInput) {
    bgColorInput.addEventListener("input", function (e) {
      var hex = $("#bg-color-hex");
      if (hex) hex.textContent = e.target.value;
    });
  }

  // Selects
  var transSelect = $("#transition-select");
  if (transSelect) transSelect.addEventListener("change", function () { renderTimeline(); drawAIFrame(0); });
  var fpsSelect = $("#fps-select");
  if (fpsSelect) fpsSelect.addEventListener("change", function () { renderTimeline(); drawAIFrame(0); });
  var aiFormat = $("#ai-format");
  if (aiFormat) aiFormat.addEventListener("change", function () { renderTimeline(); drawAIFrame(0); });

  // Dropzone (manual mode)
  var dropzone = $("#dropzone");
  var fileInput = $("#file-input");
  if (dropzone && fileInput) {
    dropzone.addEventListener("click", function () { fileInput.click(); });
    fileInput.addEventListener("change", function (e) { addImages(e.target.files); fileInput.value = ""; });
    dropzone.addEventListener("dragover", function (e) { e.preventDefault(); dropzone.classList.add("drag-over"); });
    dropzone.addEventListener("dragleave", function () { dropzone.classList.remove("drag-over"); });
    dropzone.addEventListener("drop", function (e) { e.preventDefault(); dropzone.classList.remove("drag-over"); addImages(e.dataTransfer.files); });
  }

  // Add text button (manual)
  var btnAddText = $("#btn-add-text");
  if (btnAddText) btnAddText.addEventListener("click", addTextOverlay);

  // Preview buttons
  var btnPlayEl = $("#btn-play-preview");
  if (btnPlayEl) btnPlayEl.addEventListener("click", startPreview);
  var btnStopEl = $("#btn-stop-preview");
  if (btnStopEl) btnStopEl.addEventListener("click", stopPreview);

  // Export button
  var btnExportEl = $("#btn-export");
  if (btnExportEl) btnExportEl.addEventListener("click", exportVideo);

  // Initial draw
  drawAIFrame(0);

  console.log("✅ CriativoMaker AI loaded successfully!");
});
