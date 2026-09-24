import { Route, Switch } from "wouter";
import Home from "./pages/Home";
import Catalog from "./pages/Catalog";
import Product from "./pages/Product";
import Checkout from "./pages/Checkout";

export default function App() {
  return (
    <Switch>
      <Route path="/" component={Home} />
      <Route path="/catalogo" component={Catalog} />
      <Route path="/produto/:slug" component={Product} />
      <Route path="/checkout/:slug" component={Checkout} />
    </Switch>
  );
}
