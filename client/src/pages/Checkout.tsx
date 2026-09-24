import { useEffect, useState } from "react";
import { useRoute, useLocation } from "wouter";
import { ShoppingBag, ArrowRight, Truck, Store, MapPin, WalletCards, QrCode, CreditCard, Check, ShieldCheck, Clock3 } from "lucide-react";

export default function Checkout() {
  const [match, params] = useRoute("/checkout/:slug");
  const [, setLocation] = useLocation();
  const [product, setProduct] = useState<any>(null);

  const [stage, setStage] = useState(1);
  const [delivery, setDelivery] = useState("standard");
  const [payment, setPayment] = useState("pix");
  const [done, setDone] = useState(false);
  const [form, setForm] = useState({ name: "", cpf: "", email: "", phone: "", cep: "", number: "", complement: "" });
  const update = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));

  useEffect(() => {
    if (params?.slug) {
      fetch("/api/products")
        .then((r) => r.json())
        .then((data) => {
          const p = data.find((item: any) => item.slug === params.slug);
          setProduct(p);
        })
        .catch((e) => console.error("Erro ao buscar produto:", e));
    }
  }, [params]);

  if (!product) return <div style={{ padding: 40, textAlign: "center" }}>Carregando checkout...</div>;

  const imgs = product.imageUrls ? product.imageUrls.split(",") : [];
  const next = () => setStage((current) => Math.min(3, current + 1));

  const handleFinish = async () => {
    try {
      await fetch('/api/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          order_id: '#' + Math.floor(Math.random() * 1000000),
          customer_name: form.name || 'Cliente Sem Nome',
          status: 'pending',
          type: payment,
          total: product.price
        })
      });
      setDone(true);
    } catch (e) {
      console.error(e);
      alert('Erro ao salvar pedido');
    }
  };

  return (
    <div style={{ background: "#f5f5f5", minHeight: "100vh", fontFamily: "sans-serif" }}>
      <header style={{ background: "#ffe600", padding: 15, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, fontWeight: "bold" }}>
          <ShoppingBag size={20} />
          Finalizar Compra
        </div>
      </header>

      <div style={{ maxWidth: 800, margin: "0 auto", padding: 20 }}>
        {done ? (
          <div style={{ background: "#fff", padding: 30, borderRadius: 8, textAlign: "center" }}>
            <div style={{ width: 60, height: 60, background: "#4caf50", color: "#fff", borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 20px" }}>
              <Check size={30} />
            </div>
            <h2>Pedido realizado com sucesso!</h2>
            <p>Seu pedido foi registrado no painel administrativo.</p>
            {payment === 'pix' && (
              <div style={{ margin: "20px 0" }}>
                <QrCode size={120} />
                <div style={{ marginTop: 10, padding: 10, background: "#f9f9f9", border: "1px solid #ddd", wordBreak: "break-all" }}>
                  00020126580014BR.GOV.BCB.PIX...SIMULACAO
                </div>
              </div>
            )}
            <button
              onClick={() => setLocation("/")}
              style={{ background: "#3483fa", color: "#fff", border: "none", padding: "12px 20px", borderRadius: 6, cursor: "pointer", fontWeight: "bold", marginTop: 20 }}
            >
              Voltar ao Início
            </button>
          </div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", gap: 20 }}>
            {/* Produto Resumo */}
            <div style={{ background: "#fff", padding: 15, borderRadius: 8, display: "flex", gap: 15 }}>
              <img src={imgs[0]?.trim()} alt={product.title} style={{ width: 60, height: 60, objectFit: "contain" }} />
              <div>
                <h3 style={{ fontSize: 14, margin: "0 0 5px" }}>{product.title}</h3>
                <strong style={{ color: "#333" }}>{product.price}</strong>
              </div>
            </div>

            {/* Etapa 1: Dados */}
            <div style={{ background: "#fff", padding: 20, borderRadius: 8, opacity: stage >= 1 ? 1 : 0.5 }}>
              <h3 style={{ margin: "0 0 15px", display: "flex", alignItems: "center", gap: 10 }}>1. Identificação</h3>
              {stage === 1 && (
                <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                  <input style={inputStyle} placeholder="Nome completo" value={form.name} onChange={(e) => update("name", e.target.value)} />
                  <input style={inputStyle} placeholder="CPF" value={form.cpf} onChange={(e) => update("cpf", e.target.value)} />
                  <input style={inputStyle} type="email" placeholder="E-mail" value={form.email} onChange={(e) => update("email", e.target.value)} />
                  <input style={inputStyle} placeholder="Telefone" value={form.phone} onChange={(e) => update("phone", e.target.value)} />
                  <button onClick={next} style={btnStyle}>Continuar para entrega</button>
                </div>
              )}
            </div>

            {/* Etapa 2: Entrega */}
            <div style={{ background: "#fff", padding: 20, borderRadius: 8, opacity: stage >= 2 ? 1 : 0.5 }}>
              <h3 style={{ margin: "0 0 15px", display: "flex", alignItems: "center", gap: 10 }}>2. Entrega</h3>
              {stage === 2 && (
                <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                  <input style={inputStyle} placeholder="CEP" value={form.cep} onChange={(e) => update("cep", e.target.value)} />
                  <input style={inputStyle} placeholder="Número" value={form.number} onChange={(e) => update("number", e.target.value)} />
                  <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                    <div
                      onClick={() => setDelivery("standard")}
                      style={{ flex: 1, padding: 15, border: delivery === "standard" ? "2px solid #3483fa" : "1px solid #ddd", borderRadius: 8, cursor: "pointer" }}
                    >
                      <Truck size={18} />
                      <div style={{ fontWeight: "bold", marginTop: 5 }}>Padrão</div>
                      <div style={{ color: "#4caf50", fontSize: 13, marginTop: 5 }}>Grátis</div>
                    </div>
                  </div>
                  <button onClick={next} style={btnStyle}>Continuar para pagamento</button>
                </div>
              )}
            </div>

            {/* Etapa 3: Pagamento */}
            <div style={{ background: "#fff", padding: 20, borderRadius: 8, opacity: stage >= 3 ? 1 : 0.5 }}>
              <h3 style={{ margin: "0 0 15px", display: "flex", alignItems: "center", gap: 10 }}>3. Pagamento</h3>
              {stage === 3 && (
                <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                  <div
                    onClick={() => setPayment("pix")}
                    style={{ padding: 15, border: payment === "pix" ? "2px solid #3483fa" : "1px solid #ddd", borderRadius: 8, cursor: "pointer", display: "flex", alignItems: "center", gap: 15 }}
                  >
                    <QrCode size={24} color="#3483fa" />
                    <div>
                      <div style={{ fontWeight: "bold" }}>Pix</div>
                      <div style={{ fontSize: 13, color: "#666" }}>Aprovação imediata</div>
                    </div>
                  </div>
                  <div
                    onClick={() => setPayment("card")}
                    style={{ padding: 15, border: payment === "card" ? "2px solid #3483fa" : "1px solid #ddd", borderRadius: 8, cursor: "pointer", display: "flex", alignItems: "center", gap: 15 }}
                  >
                    <CreditCard size={24} color="#3483fa" />
                    <div>
                      <div style={{ fontWeight: "bold" }}>Cartão de Crédito</div>
                      <div style={{ fontSize: 13, color: "#666" }}>Até 12x sem juros</div>
                    </div>
                  </div>
                  <button onClick={handleFinish} style={btnStyle}>
                    Finalizar Compra
                  </button>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

const inputStyle = {
  width: "100%",
  padding: "12px",
  border: "1px solid #ddd",
  borderRadius: 6,
  fontSize: 14,
  boxSizing: "border-box" as const,
};

const btnStyle = {
  width: "100%",
  padding: "15px",
  background: "#3483fa",
  color: "#fff",
  border: "none",
  borderRadius: 6,
  fontSize: 16,
  fontWeight: "bold",
  cursor: "pointer",
  marginTop: 10,
};
