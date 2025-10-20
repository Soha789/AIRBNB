<?php
// ---------- index.php ----------
// SQLite bootstrap (creates DB + seeds demo data on first run)
function getDB() {
  $db = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // properties
  $db->exec("CREATE TABLE IF NOT EXISTS properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    location TEXT NOT NULL,
    price_per_night REAL NOT NULL,
    type TEXT NOT NULL,
    amenities TEXT NOT NULL,
    rating REAL NOT NULL,
    image TEXT NOT NULL,
    description TEXT NOT NULL
  )");
  // bookings
  $db->exec("CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    guest_name TEXT NOT NULL,
    guest_email TEXT NOT NULL,
    checkin TEXT NOT NULL,
    checkout TEXT NOT NULL,
    guests INTEGER NOT NULL,
    total_price REAL NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  )");
  // seed only if empty
  $count = (int)$db->query("SELECT COUNT(*) FROM properties")->fetchColumn();
  if ($count === 0) {
    $seed = $db->prepare("INSERT INTO properties
      (title, location, price_per_night, type, amenities, rating, image, description)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $rows = [
      ["Skyline Studio", "Riyadh", 320, "Apartment", "WiFi,AC,Kitchen,Washer", 4.7, "https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?q=80&w=1200&auto=format&fit=crop", "Minimal modern studio with city views."],
      ["Palm Courtyard Villa", "Jeddah", 620, "Villa", "WiFi,Pool,Kitchen,Parking", 4.9, "https://images.unsplash.com/photo-1505692794403-34d4982d6e6a?q=80&w=1200&auto=format&fit=crop", "Spacious villa with private pool near the corniche."],
      ["Desert Dome Retreat", "AlUla", 480, "Unique stay", "WiFi,AC,FreeBreakfast,Parking", 4.8, "https://images.unsplash.com/photo-1520256862855-398228c41684?q=80&w=1200&auto=format&fit=crop", "Glamping dome under the stars—perfect for stargazing."],
      ["Beachfront Loft", "Dammam", 410, "Apartment", "WiFi,AC,Kitchen,BeachAccess", 4.6, "https://images.unsplash.com/photo-1519710164239-da123dc03ef4?q=80&w=1200&auto=format&fit=crop", "Loft with balcony overlooking the sea."],
      ["Heritage House", "Diriyah", 540, "House", "WiFi,AC,Kitchen,Washer,Parking", 4.85, "https://images.unsplash.com/photo-1505691938895-1758d7feb511?q=80&w=1200&auto=format&fit=crop", "Traditional style home near historical sights."]
    ];
    foreach ($rows as $r) $seed->execute($r);
  }
  return $db;
}
$db = getDB();

// Build filters from GET (server-side for initial load; JS can refine)
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$minPrice = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
$maxPrice = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;
$amen = isset($_GET['amen']) ? (array)$_GET['amen'] : [];
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

$query = "SELECT * FROM properties WHERE 1=1";
$params = [];
if ($location !== '') { $query .= " AND LOWER(location) LIKE ?"; $params[] = '%'.strtolower($location).'%'; }
if ($type !== '') { $query .= " AND type = ?"; $params[] = $type; }
if ($minPrice !== null) { $query .= " AND price_per_night >= ?"; $params[] = $minPrice; }
if ($maxPrice !== null) { $query .= " AND price_per_night <= ?"; $params[] = $maxPrice; }
foreach ($amen as $a) {
  $query .= " AND amenities LIKE ?";
  $params[] = '%'.$a.'%';
}
if ($sort === 'price_asc') $query .= " ORDER BY price_per_night ASC";
elseif ($sort === 'price_desc') $query .= " ORDER BY price_per_night DESC";
elseif ($sort === 'best') $query .= " ORDER BY rating DESC";
else $query .= " ORDER BY id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>StayFinder — Airbnb-like Listings</title>
<style>
  :root { --bg:#0b0c10; --card:#111216; --muted:#a7b0c0; --text:#e9eef5; --accent:#7c5cff; --accent2:#00d4ff; }
  * { box-sizing: border-box; }
  body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background: linear-gradient(180deg, #0b0c10, #111216); color: var(--text); }
  header { position: sticky; top:0; z-index:10; backdrop-filter:saturate(140%) blur(10px); background: rgba(11,12,16,0.7); border-bottom: 1px solid #1b1c22;}
  .wrap { max-width: 1150px; margin: 0 auto; padding: 18px 16px; }
  .brand { display:flex; align-items:center; gap:10px; font-weight:800; letter-spacing:.3px; }
  .brand .dot { width:10px; height:10px; border-radius:999px; background: linear-gradient(135deg, var(--accent), var(--accent2)); box-shadow:0 0 18px var(--accent2); }
  .search { display:grid; grid-template-columns: 1.1fr repeat(2, 0.9fr) 0.8fr 0.8fr 140px; gap:10px; margin-top:12px; }
  input, select, button { width:100%; padding:12px 14px; border:1px solid #1d1f27; background:#0e0f13; color:var(--text); border-radius:12px; outline:none; }
  input::placeholder { color:#7c8294; }
  button.primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); color:#0c0c12; font-weight:800; border:none; cursor:pointer; }
  .filters { display:flex; gap:12px; align-items:center; margin-top:10px; flex-wrap:wrap; color:#c8cfda; }
  .chip { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; background:#0e0f13; border:1px solid #1d1f27; border-radius:99px; }
  main { padding: 22px 16px 60px; }
  .grid { max-width:1150px; margin: 0 auto; display:grid; grid-template-columns: repeat(3, 1fr); gap:18px; }
  @media (max-width: 980px) { .grid{grid-template-columns: repeat(2,1fr);} .search{grid-template-columns: 1fr 1fr;}}
  @media (max-width: 640px) { .grid{grid-template-columns: 1fr;} .search{grid-template-columns:1fr;} }
  .card { background: linear-gradient(180deg, #0f1015, #0b0c10); border:1px solid #1b1c22; border-radius:20px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,.35); transition: transform .2s ease, box-shadow .2s ease; }
  .card:hover { transform: translateY(-3px); box-shadow: 0 20px 40px rgba(0,0,0,.45); }
  .imgwrap { aspect-ratio: 16/10; overflow:hidden; }
  .imgwrap img { width:100%; height:100%; object-fit:cover; display:block; }
  .card-body { padding:14px; }
  .title { font-weight:800; font-size:1.05rem; margin:0 0 6px; display:flex; justify-content:space-between; gap:10px;}
  .muted { color: var(--muted); font-size:.92rem; }
  .row { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:10px; }
  .price { font-weight:900; font-size:1.1rem; }
  .stars { font-size:.95rem; color:#ffd166; }
  .btn { padding:10px 12px; border-radius:12px; border:1px solid #262833; background:#0f1117; color:var(--text); cursor:pointer; }
  .btn:hover { border-color:#323546; }
  footer { text-align:center; color:#96a0b3; padding:30px 0 60px; }
  .linkrow { display:flex; gap:12px; justify-content:flex-end; margin:10px 0 0; }
  a.navlike { text-decoration:none; color:#b7c2d8; padding:8px 12px; border:1px solid #1e2230; border-radius:10px; }
  a.navlike:hover { background:#0f1117; }
</style>
</head>
<body>
<header>
  <div class="wrap">
    <div class="brand">
      <div class="dot"></div>
      <div>StayFinder</div>
    </div>
    <div class="search" id="searchBar">
      <input type="text" id="location" placeholder="Where are you going? (e.g., Riyadh)" value="<?php echo htmlspecialchars($location); ?>">
      <input type="date" id="checkin">
      <input type="date" id="checkout">
      <select id="type">
        <option value="">Any type</option>
        <?php
          $types = ["Apartment","House","Villa","Unique stay"];
          foreach ($types as $t) {
            $sel = $type===$t ? 'selected' : '';
            echo "<option $sel>".htmlspecialchars($t)."</option>";
          }
        ?>
      </select>
      <select id="sort">
        <option value="">Sort: Newest</option>
        <option value="best" <?php echo $sort==='best'?'selected':''; ?>>Best rated</option>
        <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Price ↑</option>
        <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price ↓</option>
      </select>
      <button class="primary" id="btnSearch">Search</button>
    </div>
    <div class="filters">
      <span class="chip">Min SAR <input type="number" id="min" placeholder="0" style="width:100px;margin-left:8px" value="<?php echo $minPrice!==null?$minPrice:''; ?>"></span>
      <span class="chip">Max SAR <input type="number" id="max" placeholder="1000" style="width:110px;margin-left:8px" value="<?php echo $maxPrice!==null?$maxPrice:''; ?>"></span>
      <label class="chip"><input type="checkbox" class="amen" value="WiFi"> Wi-Fi</label>
      <label class="chip"><input type="checkbox" class="amen" value="AC"> AC</label>
      <label class="chip"><input type="checkbox" class="amen" value="Kitchen"> Kitchen</label>
      <label class="chip"><input type="checkbox" class="amen" value="Pool"> Pool</label>
      <label class="chip"><input type="checkbox" class="amen" value="Parking"> Parking</label>
      <div class="linkrow">
        <a class="navlike" href="javascript:void(0)" onclick="goto('dashboard.php')">Dashboard</a>
      </div>
    </div>
  </div>
</header>

<main>
  <div class="grid" id="grid">
    <?php foreach ($properties as $p): ?>
      <div class="card">
        <div class="imgwrap">
          <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
        </div>
        <div class="card-body">
          <div class="title">
            <span><?php echo htmlspecialchars($p['title']); ?></span>
            <span class="stars">★ <?php echo number_format($p['rating'],2); ?></span>
          </div>
          <div class="muted"><?php echo htmlspecialchars($p['location']); ?> • <?php echo htmlspecialchars($p['type']); ?></div>
          <p class="muted" style="margin:8px 0 0"><?php echo htmlspecialchars($p['description']); ?></p>
          <div class="row">
            <div class="price">SAR <?php echo number_format($p['price_per_night']); ?><span class="muted">/night</span></div>
            <button class="btn" onclick="viewDetails(<?php echo (int)$p['id'];?>)">View / Book</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<footer>
  Built with ❤ — All navigation uses JavaScript (no PHP redirects). Data stored in SQLite locally.
  <div style="margin-top:10px">
    <a class="navlike" href="javascript:void(0)" onclick="goto('index.php')">Home</a>
    <a class="navlike" href="javascript:void(0)" onclick="goto('dashboard.php')">Dashboard</a>
  </div>
</footer>

<script>
// Preserve GET checkboxes if present
(function syncAmenFromURL(){
  const url = new URL(window.location.href);
  const amens = url.searchParams.getAll('amen[]').concat(url.searchParams.getAll('amen'));
  document.querySelectorAll('.amen').forEach(cb => { cb.checked = amens.includes(cb.value); });
})();

function goto(path){ location.href = path; }

// Build URL and redirect via JS
document.getElementById('btnSearch').addEventListener('click', () => {
  const url = new URL(location.origin + location.pathname);
  url.searchParams.set('location', document.getElementById('location').value.trim());
  url.searchParams.set('type', document.getElementById('type').value);
  url.searchParams.set('sort', document.getElementById('sort').value);
  const min = document.getElementById('min').value; if(min!=='') url.searchParams.set('min', min);
  const max = document.getElementById('max').value; if(max!=='') url.searchParams.set('max', max);
  document.querySelectorAll('.amen:checked').forEach(cb => url.searchParams.append('amen', cb.value));
  // dates are for bookings page, but we include them so "View/Book" can pick them up if set
  const ci = document.getElementById('checkin').value, co = document.getElementById('checkout').value;
  if(ci) url.searchParams.set('checkin', ci);
  if(co) url.searchParams.set('checkout', co);
  location.href = url.toString();
});

// View/Book navigates with current dates
function viewDetails(id){
  const urlSrc = new URL(window.location.href);
  const ci = urlSrc.searchParams.get('checkin') || '';
  const co = urlSrc.searchParams.get('checkout') || '';
  const url = new URL(location.origin + location.pathname.replace('index.php','') + 'bookings.php');
  url.searchParams.set('id', id);
  if(ci) url.searchParams.set('checkin', ci);
  if(co) url.searchParams.set('checkout', co);
  location.href = url.toString();
}
</script>
</body>
</html>
