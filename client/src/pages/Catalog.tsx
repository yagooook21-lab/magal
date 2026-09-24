import { useEffect, useState } from "react";
import { Link } from "wouter";

export default function Catalog() {
  const [products, setProducts] = useState<any[]>([]);

  useEffect(() => {
    fetch("/api/products")
      .then((r) => r.json())
      .then((data) => setProducts(data))
      .catch((e) => console.error("Erro ao buscar catálogo:", e));
  }, []);

  return (
    <div style={{ maxWidth: 800, margin: "0 auto", padding: 20 }}>
      <h1 style={{ fontSize: 24, marginBottom: 20 }}>Catálogo de Produtos</h1>
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))",
          gap: 20,
        }}
      >
        {products.map((p) => {
          const imgs = p.imageUrls ? p.imageUrls.split(",") : [];
          return (
            <Link key={p.id} href={`/produto/${p.slug}`}>
              <div
                style={{
                  border: "1px solid #eee",
                  borderRadius: 8,
                  padding: 10,
                  cursor: "pointer",
                }}
              >
                <img
                  src={imgs[0]?.trim()}
                  alt={p.title}
                  style={{
                    width: "100%",
                    height: 150,
                    objectFit: "contain",
                  }}
                />
                <h3 style={{ fontSize: 14, margin: "10px 0" }}>{p.title}</h3>
                <strong style={{ color: "#d32f2f" }}>{p.price}</strong>
              </div>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
