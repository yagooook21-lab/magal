import { Router, json } from 'express';
import { all, run, get } from './db.js'; // db is configured with esm output

const api = Router();
api.use(json());

// --- Produtos ---
api.get('/products', async (req, res) => {
  try {
    const products = await all('SELECT * FROM products ORDER BY id DESC');
    res.json(products);
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

api.post('/products', async (req, res) => {
  try {
    const { title, slug, brand, price, originalPrice, rating, reviewsCount, category, description, imageUrls, variations, reviews } = req.body;
    await run(`INSERT INTO products (title, slug, brand, price, originalPrice, rating, reviewsCount, category, description, imageUrls, variations, reviews) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`, 
      [title, slug, brand, price, originalPrice, rating, reviewsCount, category, description, imageUrls, variations, reviews]
    );
    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

// --- Pedidos ---
api.get('/orders', async (req, res) => {
  try {
    const orders = await all('SELECT * FROM orders ORDER BY id DESC');
    res.json(orders);
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

api.post('/orders', async (req, res) => {
  try {
    const { order_id, customer_name, status, type, total } = req.body;
    await run(`INSERT INTO orders (order_id, customer_name, status, type, total) VALUES (?, ?, ?, ?, ?)`, 
      [order_id, customer_name, status, type, total]
    );
    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

// --- Pixels ---
api.get('/pixels', async (req, res) => {
  try {
    const pixels = await all('SELECT * FROM pixels');
    res.json(pixels);
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

api.post('/pixels', async (req, res) => {
  try {
    const { name, pixel_id } = req.body;
    await run(`INSERT INTO pixels (name, pixel_id) VALUES (?, ?)`, [name, pixel_id]);
    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

// --- Gateways ---
api.get('/gateways', async (req, res) => {
  try {
    const gateways = await all('SELECT * FROM gateways');
    res.json(gateways);
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

api.post('/gateways/activate', async (req, res) => {
  try {
    const { name } = req.body;
    await run(`UPDATE gateways SET is_active = 0`);
    
    // Check if exists
    const existing = await get(`SELECT id FROM gateways WHERE name = ?`, [name]);
    if (existing) {
      await run(`UPDATE gateways SET is_active = 1 WHERE name = ?`, [name]);
    } else {
      await run(`INSERT INTO gateways (name, is_active) VALUES (?, 1)`, [name]);
    }
    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ error: String(error) });
  }
});

export default api;
