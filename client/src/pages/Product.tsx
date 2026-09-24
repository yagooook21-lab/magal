import { useEffect, useState } from "react";
import { useRoute, useLocation } from "wouter";
import { ShoppingBag } from "lucide-react";

export default function Product() {
  const [match, params] = useRoute("/produto/:slug");
  const [, setLocation] = useLocation();
  const [product, setProduct] = useState<any>(null);

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

  if (!product) {
    return <div style={{ padding: 40, textAlign: "center" }}>Carregando produto...</div>;
  }

  const imgs = product.imageUrls ? product.imageUrls.split(",") : [];

  return (
    <div style={{ maxWidth: 400, margin: "0 auto", background: "#fff", minHeight: "100vh" }}>
      <img
        src={imgs[0]?.trim()}
        alt={product.title}
        style={{ width: "100%", maxHeight: 300, objectFit: "contain", background: "#fff" }}
      />
      <div style={{ padding: 20 }}>
        <span style={{ background: "#4caf50", color: "#fff", padding: "2px 8px", borderRadius: 4, fontSize: 12, fontWeight: "bold" }}>Full</span>
        <h1 style={{ fontSize: 18, margin: "10px 0" }}>{product.title}</h1>
        <div style={{ color: "#666", fontSize: 13, marginBottom: 15 }}>
          {product.brand || "Magalu"} · {product.rating || "5.0"} ({product.reviewsCount || "0"} avaliações)
        </div>
        <div style={{ fontSize: 28, color: "#333" }}>{product.price}</div>
        {product.originalPrice && (
          <div style={{ color: "#999", textDecoration: "line-through", fontSize: 14 }}>
            R$ {product.originalPrice}
          </div>
        )}

        <button
          onClick={() => setLocation(`/checkout/${product.slug}`)}
          style={{
            width: "100%",
            background: "#3483fa",
            color: "#fff",
            border: "none",
            padding: 15,
            borderRadius: 6,
            fontSize: 16,
            fontWeight: "bold",
            marginTop: 20,
            cursor: "pointer",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            gap: 10
          }}
        >
          <ShoppingBag size={18} /> Comprar agora
        </button>

        <div style={{ marginTop: 30 }}>
          <h3 style={{ fontSize: 16, borderBottom: "1px solid #eee", paddingBottom: 10 }}>Descrição</h3>
          <p style={{ color: "#444", fontSize: 14, lineHeight: "1.5", whiteSpace: "pre-line", marginTop: 10 }}>
            {product.description || "Sem descrição disponível."}
          </p>
        </div>
      </div>
    </div>
  );
}
