const { createElement, useState, useEffect } = React;
const { createRoot } = ReactDOM;
const {
  HashRouter,
  Switch,
  Route,
  Link,
  useParams,
  useHistory
} = ReactRouterDOM;

function normalizeName(str) {
  return str.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
}

function Sparkline({ id, data }) {
  useEffect(() => {
    if (!Array.isArray(data) || data.length < 2) return;

    const ctx = document.getElementById(id);
    if (!ctx) return;

    // Determine color: red if price dropped, green if up or flat
    let borderColor = "green";
    if (data[data.length - 1] < data[0]) borderColor = "red";

    // Destroy previous chart if exists
    if (ctx._chartInstance) {
      ctx._chartInstance.destroy();
    }

    const chart = new Chart(ctx, {
      type: "line",
      data: {
        labels: data.map((_, i) => i),
        datasets: [{
          data: data,
          borderColor,
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
    ctx._chartInstance = chart;

    return () => {
      chart.destroy();
      ctx._chartInstance = null;
    };
  }, [id, data]);

  return createElement("canvas", { id, width: 120, height: 40 });
}

function CardThumbnail({ card, sparklineData, idx }) {
  // Fallback handler for image error
  const handleImgError = (e) => {
    if (card.image_uris?.normal && e.target.src !== card.image_uris.normal) {
      e.target.src = card.image_uris.normal;
    }
  };
  return createElement("div", {},
    createElement("div", {
      className: "card-thumbnail",
      onClick: (e) => {
        if (window.matchMedia("(hover: none)").matches) {
          e.currentTarget.classList.toggle("show-popup");
        }
      }
    },
      createElement("img", {
        src: card.image_uri || card.image_uris?.normal,
        alt: card.name,
        onError: handleImgError
      }),
      createElement("div", { className: "card-popup" },
        createElement("img", {
          src: card.image_uri || card.image_uris?.normal,
          alt: card.name,
          onError: handleImgError
        })
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
        id: `spark-${card.id || card.name || idx}`,
        data: sparklineData
      })
    )
  );
}

function batchFetchCardsByNames(names, callback) {
  if (!names.length) return callback([]);
  // Scryfall supports up to 75 terms per search, so chunk if needed
  const chunks = [];
  for (let i = 0; i < names.length; i += 75) {
    chunks.push(names.slice(i, i + 75));
  }
  Promise.all(chunks.map(chunk =>
    fetch(`/api/scryfall-proxy.php?search=${encodeURIComponent(chunk.map(n => `!"${n}"`).join(' OR '))}`)
      .then(res => res.json())
      .then(data => Array.isArray(data.data) ? data.data : [])
      .catch(() => [])
  )).then(results => {
    // Flatten and callback
    callback([].concat(...results));
  });
}

function HomePage() {
  const [trending, setTrending] = useState([]);
  const [surging, setSurging] = useState([]);
  const [topEdhrec, setTopEdhrec] = useState([]);
  const [historyMap, setHistoryMap] = useState({});
  const [search, setSearch] = useState("");
  const history = useHistory();

  useEffect(() => {
    // Add loading states
    setTrending(null);
    setSurging(null);
    setTopEdhrec(null);

    fetch("/api/recent_cards.php")
      .then(res => res.json())
      .then(names => {
        if (!Array.isArray(names)) return setTrending([]);
        batchFetchCardsByNames(names.map(c => c.name || c), setTrending);
      })
      .catch(() => setTrending([]));

    fetch("/api/price_history.php")
      .then(res => res.json())
      .then(trends => {
        const all = Object.entries(trends)
          .map(([name, entry]) => {
            const data = entry.prices;
            const len = data.length;
            if (len < 2) return null;
            const prev = data[len - 2];
            const curr = data[len - 1];
            const change = prev > 0 ? (curr - prev) / prev : 0;
            return { name, change };
          })
          .filter(Boolean)
          .sort((a, b) => Math.abs(b.change) - Math.abs(a.change))
          .slice(0, 10)
          .map(obj => obj.name);
        setHistoryMap(trends);
        batchFetchCardsByNames(all, setSurging);
      })
      .catch(() => setSurging([]));

    fetch("/api/top_edhrec.php")
      .then(res => res.json())
      .then(data => {
        const seen = new Set();
        const unique = data.filter(c => !seen.has(c.name) && seen.add(c.name));
        batchFetchCardsByNames(unique.slice(0, 10).map(c => c.name), setTopEdhrec);
      })
      .catch(() => setTopEdhrec([]));
  }, []);

  const handleSearch = (e) => {
    e.preventDefault();
    if (search.trim()) {
      history.push(`/search/${normalizeName(search)}`);
    }
  };

  const renderGrid = (cards) => {
    if (cards === null) {
      return createElement("div", { style: { textAlign: "center", padding: "30px" } }, "Loading...");
    }
    if (!cards.length) {
      return createElement("div", { style: { textAlign: "center", padding: "30px" } }, "No cards found.");
    }
    return createElement("div", { className: "card-grid" },
      cards.map((card, idx) =>
        createElement(Link, {
          to: `/card/${normalizeName(card.name)}`,
          key: card.id || card.name || idx
        },
          createElement(CardThumbnail, {
            card,
            sparklineData: historyMap[card.name]?.prices,
            idx
          })
        )
      )
    );
  };

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
    createElement("h2", null, "📉📈 Rapid Price Changes"),
    renderGrid(surging),
    createElement("h2", null, "🏆 Top EDHREC Cards"),
    renderGrid(topEdhrec)
  );
}

function CardDetailPage() {
  const { name } = useParams();
  const [versions, setVersions] = useState([]);

  useEffect(() => {
    fetch(`/api/versions.php?name=${encodeURIComponent(name)}`)
      .then(res => res.json())
      .then(setVersions);
  }, [name]);

  return createElement("div", { style: { padding: "20px" } },
    createElement("h2", null, `Versions of ${name.replace(/-/g, ' ')}`),
    createElement("div", { className: "card-grid" },
      versions.map(card =>
        createElement("div", { key: card.id },
          createElement("img", {
            src: card.image_uri || card.image_uris?.normal,
            alt: card.name,
            style: { width: "150px", border: "1px solid #333", borderRadius: "6px" },
            onError: (e) => {
              if (card.image_uris?.normal && e.target.src !== card.image_uris.normal) {
                e.target.src = card.image_uris.normal;
              }
            }
          }),
          createElement("p", null, `${card.set_name} - ${card.rarity.toUpperCase()} #${card.collector_number}`),
          card.prices?.usd && createElement("p", null, `Non-Foil: $${parseFloat(card.prices.usd).toFixed(2)}`),
          card.prices?.usd_foil && createElement("p", null, `Foil: $${parseFloat(card.prices.usd_foil).toFixed(2)}`)
        )
      )
    )
  );
}

function BackHomeButtons() {
  const history = useHistory();
  return createElement('div', { style: { marginBottom: '20px' } },
    createElement('button', {
      onClick: () => history.goBack(),
      style: { marginRight: '10px', padding: '6px 16px' }
    }, '← Back'),
    createElement(Link, {
      to: '/',
      style: { textDecoration: 'none' }
    },
      createElement('button', { style: { padding: '6px 16px' } }, '🏠 Home')
    )
  );
}

function VersionsPage() {
  const { name } = useParams();
  const [versions, setVersions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetch(`/api/scryfall-proxy.php?search=!\"${encodeURIComponent(name.replace(/-/g, ' '))}\"&unique=prints`)
      .then(res => {
        if (!res.ok) throw new Error('Not found');
        return res.json();
      })
      .then(data => {
        // Scryfall search returns {data: [...]} or an error
        if (Array.isArray(data.data)) setVersions(data.data);
        else setError('No versions found.');
      })
      .catch(() => setError('No versions found.'))
      .finally(() => setLoading(false));
  }, [name]);

  if (loading) return createElement('div', { style: { textAlign: 'center', padding: '30px' } }, 'Loading...');
  if (error) return createElement('div', { style: { textAlign: 'center', padding: '30px' } }, error);
  if (!versions.length) return createElement('div', { style: { textAlign: 'center', padding: '30px' } }, 'No versions found.');

  return createElement('div', { style: { padding: '20px' } },
    createElement(BackHomeButtons, null),
    createElement('h2', null, `All Printings of ${name.replace(/-/g, ' ')}`),
    createElement('div', { className: 'card-grid' },
      versions.map((card, idx) =>
        createElement(CardThumbnail, { card, idx, key: card.id || card.name || idx })
      )
    )
  );
}

function SearchResultsPage() {
  const { name } = useParams();
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const history = useHistory();

  useEffect(() => {
    setLoading(true);
    setError(null);
    // Use Scryfall search endpoint for fuzzy/multi results
    fetch(`/api/scryfall-proxy.php?search=${encodeURIComponent(name.replace(/-/g, ' '))}`)
      .then(res => {
        if (!res.ok) throw new Error('Not found');
        return res.json();
      })
      .then(data => {
        if (Array.isArray(data.data)) setResults(data.data);
        else if (data.object === 'card') setResults([data]);
        else setError('No cards found.');
      })
      .catch(() => setError('No cards found.'))
      .finally(() => setLoading(false));
  }, [name]);

  if (loading) return createElement('div', { style: { textAlign: 'center', padding: '30px' } }, 'Loading...');
  if (error) return createElement('div', { style: { textAlign: 'center', padding: '30px' } }, error);
  if (!results.length) return createElement('div', { style: { textAlign: 'center', padding: '30px' } }, 'No cards found.');

  return createElement('div', { style: { padding: '20px' } },
    createElement(BackHomeButtons, null),
    createElement('h2', null, `Search Results for "${name.replace(/-/g, ' ')}"`),
    createElement('div', { className: 'card-grid' },
      results.map((card, idx) =>
        createElement('div', {
          style: { cursor: 'pointer' },
          key: card.id || card.name || idx,
          onClick: () => history.push(`/versions/${normalizeName(card.name)}`)
        },
          createElement(CardThumbnail, { card, idx })
        )
      )
    ),
    createElement('p', null, 'Click any image to view all printings of that card.')
  );
}

const root = createRoot(document.getElementById("root"));
root.render(
  createElement(HashRouter, null,
    createElement(Switch, null,
      createElement(Route, { exact: true, path: '/', component: HomePage }),
      createElement(Route, { exact: true, path: '/card/:name', component: CardDetailPage }),
      createElement(Route, { exact: true, path: '/search/:name', component: SearchResultsPage }),
      createElement(Route, { exact: true, path: '/versions/:name', component: VersionsPage })
    )
  )
);

