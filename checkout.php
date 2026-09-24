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

// Configuração do Magalu design system
$magalu_blue = "#0086ff";
$bg_gray = "#f5f5f5";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout - <?php echo htmlspecialchars($nome_loja); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: <?php echo $bg_gray; ?>; color: #333; -webkit-font-smoothing: antialiased; }
        
        .checkout-container { max-width: 600px; margin: 0 auto; padding: 20px 15px; }
        
        /* HEADER VOLTAR */
        .header-voltar { display: inline-flex; align-items: center; margin-bottom: 15px; font-size: 14px; font-weight: 600; color: <?php echo $magalu_blue; ?>; text-decoration: none; }
        .header-voltar i { margin-right: 6px; }
        
        /* BANNER */
        .banner-container { width: 100%; border-radius: 8px; overflow: hidden; margin-bottom: 20px; background-color: #e5e5e5; min-height: 120px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc; }
        .banner-container img { width: 100%; display: block; object-fit: cover; }
        
        /* CARDS */
        .card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .card-title { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 16px; }
        
        /* PRODUCT */
        .product-item { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px; }
        .product-img { width: 70px; height: 70px; object-fit: contain; border: 1px solid #eee; border-radius: 4px; }
        .product-details { flex: 1; }
        .product-name { font-size: 13px; line-height: 1.4; color: #333; margin-bottom: 10px; font-weight: 500; }
        
        .qty-price-row { display: flex; justify-content: space-between; align-items: center; }
        .qty-selector { display: inline-flex; align-items: center; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; height: 30px; }
        .qty-btn { background: #fff; border: none; width: 30px; height: 100%; font-size: 16px; color: <?php echo $magalu_blue; ?>; cursor: pointer; }
        .qty-input { width: 40px; border: none; text-align: center; font-size: 14px; font-weight: 600; }
        .product-price { font-size: 14px; font-weight: 700; color: #333; }
        
        .total-produtos-row { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #eee; font-size: 14px; color: #333; font-weight: 500; }
        
        /* FORMS */
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 12px; color: #666; margin-bottom: 6px; }
        .form-control { width: 100%; height: 44px; border: 1px solid #ccc; border-radius: 6px; padding: 0 12px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: <?php echo $magalu_blue; ?>; box-shadow: 0 0 0 1px <?php echo $magalu_blue; ?>; }
        
        /* SUMMARY */
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #333; }
        .summary-total { display: flex; justify-content: space-between; padding-top: 15px; margin-top: 5px; border-top: 1px solid #eee; font-size: 16px; font-weight: 700; color: #333; }
        
        /* BUTTON */
        .btn-continue { width: 100%; height: 48px; background-color: <?php echo $magalu_blue; ?>; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: background-color 0.2s; }
        .btn-continue:hover { background-color: #0073e6; }
        .btn-continue:disabled { opacity: 0.7; cursor: not-allowed; }
        
        /* SECURE FOOTER */
        .secure-footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        .secure-title { color: #00a650; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 5px; margin-bottom: 10px; font-size: 13px; }
        .payment-methods { display: flex; justify-content: center; gap: 10px; margin-top: 10px; margin-bottom: 15px; }
        .payment-methods img { height: 20px; object-fit: contain; }
        .ssl-info { color: #00a650; display: flex; align-items: center; justify-content: center; gap: 5px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="checkout-container">
        <!-- Voltar -->
        <a href="javascript:history.back()" class="header-voltar">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>

        <!-- Banner Placeholder -->
        <div class="banner-container">
            <!-- A imagem do banner vai aqui. Depois podemos buscar do banco de dados ou painel. -->
            <span style="color: #888; font-size: 14px; font-weight: 500;">[ Espaço para o Banner Promocional ]</span>
        </div>

        <form id="checkoutForm" onsubmit="proceed(); return false;">
            <!-- Produto -->
            <div class="card">
                <div class="product-item">
                    <img src="<?php echo $img_src; ?>" class="product-img">
                    <div class="product-details">
                        <div class="product-name"><?php echo htmlspecialchars($nomeproduto); ?></div>
                        <div class="qty-price-row">
                            <div class="qty-selector">
                                <button type="button" class="qty-btn" onclick="updateQty(-1)">-</button>
                                <input type="number" class="qty-input" value="1" id="cart-qty" readonly>
                                <button type="button" class="qty-btn" onclick="updateQty(1)">+</button>
                            </div>
                            <div class="product-price">R$ <span id="price-unit-display"><?php echo number_format($valor_num, 2, ',', '.'); ?></span></div>
                        </div>
                    </div>
                </div>
                <div class="total-produtos-row">
                    <span>Total dos produtos</span>
                    <span id="labelTotalProdutos">R$ <?php echo number_format($valor_num, 2, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Identificação -->
            <div class="card">
                <div class="card-title">Identificação</div>
                <div class="form-group">
                    <label class="form-label">Nome completo</label>
                    <input type="text" id="nome" class="form-control" placeholder="Como no documento" required>
                </div>
                <div class="form-group">
                    <label class="form-label">CPF</label>
                    <input type="text" id="cpf" class="form-control" placeholder="000.000.000-00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" id="email" class="form-control" placeholder="voce@email.com" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Telefone</label>
                    <input type="tel" id="telefone" class="form-control" placeholder="(11) 99999-9999" required>
                </div>
            </div>

            <!-- Endereço -->
            <div class="card">
                <div class="card-title">Endereço de entrega</div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">CEP</label>
                    <input type="text" id="cep" class="form-control" placeholder="00000-000" required>
                </div>
                <!-- Campos Extras Carregados Automaticamente -->
                <div id="endereco-extra" style="display: none; margin-top: 15px;">
                    <div class="form-group">
                        <label class="form-label">Rua / Avenida</label>
                        <input type="text" id="rua" class="form-control" required>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Número</label>
                            <input type="text" id="numero" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Bairro</label>
                            <input type="text" id="bairro" class="form-control" required>
                        </div>
                    </div>
                    <div style="display: flex; gap: 15px; margin-bottom: 0;">
                        <div class="form-group" style="flex: 2; margin-bottom: 0;">
                            <label class="form-label">Cidade</label>
                            <input type="text" id="cidade" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label class="form-label">Estado</label>
                            <input type="text" id="estado" class="form-control" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumo -->
            <div class="card" style="margin-bottom: 10px;">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="summarySubtotal">R$ <?php echo number_format($valor_num, 2, ',', '.'); ?></span>
                </div>
                <div class="summary-row">
                    <span>Frete</span>
                    <span>—</span>
                </div>
                <div class="summary-total">
                    <span>Total</span>
                    <span id="summaryTotal">R$ <?php echo number_format($valor_num, 2, ',', '.'); ?></span>
                </div>
            </div>

            <button type="submit" class="btn-continue" id="btnSubmit">Continuar</button>
        </form>

        <!-- Footer Seguro -->
        <div class="card secure-footer" style="background: transparent; border: 1px solid #e0e0e0; margin-top: 15px;">
            <div class="secure-title"><i class="fa-solid fa-lock"></i> COMPRA 100% SEGURA</div>
            <div style="font-size: 11px; margin-top: 5px;">Formas de pagamento aceitas:</div>
            <div class="payment-methods">
                <img src="https://logospng.org/download/pix/logo-pix-icone-1024.png" alt="Pix" style="height:18px;">
                <img src="https://logospng.org/download/visa/logo-visa-2048.png" alt="Visa">
                <img src="https://logospng.org/download/mastercard/logo-mastercard-1024.png" alt="Mastercard">
                <img src="https://logospng.org/download/elo/logo-elo-1024.png" alt="Elo">
                <img src="https://logospng.org/download/google-pay/logo-google-pay-1024.png" alt="GPay">
            </div>
            <div class="ssl-info">
                <i class="fa-solid fa-shield-check"></i> <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#00a650" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> Seus dados são protegidos com criptografia SSL.
            </div>
        </div>
    </div>

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
            $('#btnSubmit').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Aguarde...');

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
                    $('#btnSubmit').prop('disabled', false).html('Continuar');
                    alert('Não foi possível salvar seus dados.\n' + String(retorno).trim());
                }
            }).fail(function(){
                $('#btnSubmit').prop('disabled', false).html('Continuar');
                alert('Erro na conexão. Verifique sua internet.');
            });
        }
    </script>
</body>
</html>
