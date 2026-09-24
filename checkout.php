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
$img_src = (strpos((string)$img, 'http') === 0) ? $img : "./arquivos/produtos/$codigo/$img";

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
                        input: '#e5e7eb'
                    }
                }
            }
        }
    </script>
    
    <style>
        body { background-color: #f9fafb; -webkit-font-smoothing: antialiased; }
        .hidden-address { display: none; margin-top: 12px; }
        /* Corrige o input outline focus default do tailwind nas bordas */
        input:focus { outline: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto p-4 space-y-4">
        <div class="space-y-2">
            <!-- Banner da Loja / Promoção -->
            <section aria-label="Destaques do checkout" class="w-full">
                <!-- Se quiser colocar a imagem de banner, ela entra aqui -->
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
                    
                    <div id="endereco-extra" class="hidden-address space-y-3">
                        <label class="block text-sm">
                            <span class="block text-xs font-semibold mb-1 text-muted-foreground">Rua / Avenida</span>
                            <div class="relative">
                                <input id="rua" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                            </div>
                        </label>
                        <div class="flex gap-3">
                            <label class="block text-sm flex-1">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">Número</span>
                                <div class="relative">
                                    <input id="numero" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
                            <label class="block text-sm flex-1">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">Bairro</span>
                                <div class="relative">
                                    <input id="bairro" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
                        </div>
                        <div class="flex gap-3">
                            <label class="block text-sm flex-[2]">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">Cidade</span>
                                <div class="relative">
                                    <input id="cidade" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
                            <label class="block text-sm flex-1">
                                <span class="block text-xs font-semibold mb-1 text-muted-foreground">Estado</span>
                                <div class="relative">
                                    <input id="estado" required class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 border-input focus:ring-primary" type="text">
                                </div>
                            </label>
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
                        <span class="text-muted-foreground">—</span>
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

        function formatarMoeda(valor) {
            return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function updateQty(change) {
            let qty = parseInt($('#cart-qty').val()) + change;
            if (qty < 1) qty = 1;
            $('#cart-qty').val(qty);
            
            const total = qty * valorUnitario;
            const totalFmt = 'R$ ' + formatarMoeda(total);
            
            $('#labelTotalProdutos').text(totalFmt);
            $('#summarySubtotal').text(totalFmt);
            $('#summaryTotal').text(totalFmt);

            localStorage.setItem('lojavirtual', JSON.stringify({
                quantos: qty,
                precoFinal: formatarMoeda(total)
            }));
        }

        $(document).ready(function(){
            // Inicializa localStorage
            localStorage.setItem('lojavirtual', JSON.stringify({
                quantos: 1,
                precoFinal: formatarMoeda(valorUnitario)
            }));

            // Mascaras
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

            // Busca CEP
            $('#cep').on('blur', function(){
                const cep = $(this).val().replace(/\D/g, '');
                if(cep.length === 8) {
                    $.getJSON('https://viacep.com.br/ws/' + cep + '/json/', function(json){
                        if(!json.erro) {
                            $('#rua').val(json.logradouro || '');
                            $('#bairro').val(json.bairro || '');
                            $('#cidade').val(json.localidade || '');
                            $('#estado').val(json.uf || '');
                            $('#endereco-extra').slideDown();
                            $('#numero').focus();
                        }
                    });
                }
            });
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
                complemento: '',
                referencia: '',
                tipo: 'casa'
            };
            
            if(formData.cpf.length !== 11) {
                alert("Por favor, digite um CPF válido com 11 dígitos.");
                return;
            }

            localStorage.setItem('cliente_dados', JSON.stringify(formData));
            
            // Estado de loading
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
