const { createElement, useState, useEffect } = React;
const { createRoot } = ReactDOM;
const {
  HashRouter,
  Switch,
  Route,
  Link,
  withRouter,
  useHistory
} = ReactRouterDOM;

function normalizeName(str) {
  return str.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
}

function Sparkline({ id, data }) {
  useEffect(() => {
    if (!data || data.length < 2) return;
    const ctx = document.getElementById(id);
    if (!ctx) return;

    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.map((_, i) => i),
        datasets: [{
          data: data,
          borderColor: "green",
          borderWidth: 1,
          fill: false,
          tension: 0.3
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        elements: { point: { radius: 0 } },
        scales: { x: { display: false }, y: { display: false } }
      }
    });
  }, [id, data]);

  return createElement("canvas", { id, width: 120, height: 40 });
}

function CardThumbnail({ card, sparklineData }) {
  return createElement("div", {},
    createElement("div", {
      className: "card-thumbnail",
      onClick: (e) => {
        if (window.matchMedia("(hover: none)").matches) {
          e.currentTarget.classList.toggle("show-popup");
        }
      }
    },
      createElement("img", { src: card.image, alt: card.name }),
      createElement("div", { className: "card-popup" },
        createElement("img", { src: card.image, alt: card.name })
      )
    ),
    createElement("div", { style: { maxWidth: "150px" } },
      createElement("p", null, card.name),
      card.set_name && createElement("p", null, card.set_name),
      card.rarity && card.collector_number &&
        createElement("p", null, `${card.rarity.toUpperCase()} #${card.collector_number}`),
      (card.prices?.usd || card.prices?.usd_foil) && createElement("div", null,
        card.prices.usd && createElement("p", null, `Non-Foil: $${parseFloat(card.prices.usd).toFixed(2)}`),
        card.prices.usd_foil && createElement("p", null, `Foil: $${parseFloat(card.prices.usd_foil).toFixed(2)}`)
      ),
      sparklineData && createElement(Sparkline, {
        id: `spark-${card.id}`,
        data: sparklineData
      })
    )
  );
}

function HomePage() {
  const [trending, setTrending] = useState([]);
  const [surging, setSurging] = useState([]);
  const [topEdhrec, setTopEdhrec] = useState([]);
  const [historyMap, setHistoryMap] = useState({});
  const [search, setSearch] = useState("");
  const history = useHistory();

  useEffect(() => {
    // Trending: from DB
    fetch("/api/recent_cards.php")
      .then(res => res.json())
      .then(setTrending);

    // Price Trends
    fetch("/data/price-history.json")
      .then(res => res.json())
      .then(data => {
        const rising = data.filter(entry => {
          const { previous_price, current_price } = entry;
          return previous_price > 0 && ((current_price - previous_price) / previous_price) > 0.25;
        });

        const map = {};
        data.forEach(entry => {
          map[entry.name] = entry.history || [];
        });
        setHistoryMap(map);

        Promise.all(rising.map(entry =>
          fetch(`/api/scryfall-proxy.php?name=${encodeURIComponent(entry.name)}`)
            .then(res => res.json())
            .then(card => ({
              id: card.id,
              name: card.name,
              image: card.image_uris?.normal,
              prices: card.prices,
              set_name: card.set_name,
              rarity: card.rarity,
              collector_number: card.collector_number
            }))
        )).then(setSurging);
      });

    // EDHREC Top
    fetch("/api/top_edhrec.php")
      .then(res => res.json())
      .then(data => {
        const uniqueNames = new Set();
        const unique = data.filter(card => {
          if (uniqueNames.has(card.name)) return false;
          uniqueNames.add(card.name);
          return true;
        });
        setTopEdhrec(unique.slice(0, 10));
      });
  }, []);

  const handleSearch = (e) => {
    e.preventDefault();
    if (search.trim()) {
      history.push(`/search/${normalizeName(search)}`);
    }
  };

  const renderGrid = (cards) =>
    createElement("div", { className: "card-grid" },
      cards.map(card =>
        createElement(Link, {
          to: `/card/${normalizeName(card.name)}`,
          key: card.id
        },
          createElement(CardThumbnail, {
            card,
            sparklineData: historyMap[card.name]
          })
        )
      )
    );

  return createElement("div", { style: { padding: "20px" } },
    createElement("h1", null, "Magic Card Singles"),
    createElement("form", { onSubmit: handleSearch, style: { marginBottom: "20px" } },
      createElement("input", {
        type: "text",
        value: search,
        onChange: (e) => setSearch(e.target.value),
        placeholder: "Search for a card...",
        style: { padding: "8px", width: "250px", marginRight: "10px" }
      }),
      createElement("button", { type: "submit" }, "Search")
    ),
    createElement("h2", null, "🆕 Recently Printed"),
    renderGrid(trending),
    createElement("h2", null, "🚀 Rapid Price Increases"),
    renderGrid(surging),
    createElement("h2", null, "🏆 Top EDHREC Cards"),
    renderGrid(topEdhrec)
  );
}

// Keep your Search and Version pages here...
// (If you need help adjusting them, I can include them in a follow-up)

const root = createRoot(document.getElementById("root"));
root.render(
  createElement(HashRouter, null,
    createElement(Switch, null,
      createElement(Route, { exact: true, path: "/", component: HomePage })
    )
  )
);
