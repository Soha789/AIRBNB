<?php
// ---------- dashboard.php ----------
function getDB() {
  $db = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $db;
}
$db = getDB();

// Handle add property via POST (form submit on same page; JS stays here)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__add_property'])) {
  $title = trim($_POST['title'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $type = trim($_POST['type'] ?? 'Apartment');
  $amen = trim($_POST['amenities'] ?? 'WiFi,AC');
  $rating = (float)($_POST['rating'] ?? 4.5);
  $image = trim($_POST['image'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  if ($title && $location && $price && $type && $amen && $image && $desc) {
    $stmt = $db->prepare("INSERT INTO properties (title, location, price_per_night, type, amenities, rating, image, description)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title,$location,$price,$type,$amen,$rating,$image,$desc]);
    $added = true;
  } else {
    $error = "Please fill all fields.";
  }
}

// Query data
$props = $db->query("SELECT * FROM properties ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$bookings = $db->query("SELECT b.*, p.title FROM bookings b JOIN properties p ON p.id=b.property_id ORDER BY b.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$ref = $_GET['ref'] ?? '';
$bid = $_GET['bid'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Dashboard — StayFinder</title>
<style>
  :root { --bg:#0b0c10; --card:#111216; --muted:#a7b0c0; --text:#e9eef5; --accent:#7c5cff; --accent2:#00d4ff; }
  body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background: conic-gradient(from 180deg at 50% -20%, rgba(124,92,255,.18), transparent), #0b0c10; color: var(--text); }
  header { position: sticky; top:0; z-index:10; backdrop-filter:saturate(140%) blur(10px); background: rgba(11,12,16,0.7); border-bottom: 1px solid #1b1c22; }
  .wrap { max-width: 1150px; margin: 0 auto; padding: 18px 16px; }
  .brand { display:flex; align-items:center; gap:10px; font-weight:800; }
  .brand .dot { width:10px; height:10px; border-radius:999px; background: linear-gradient(135deg, var(--accent), var(--accent2)); box-shadow:0 0 18px var(--accent2); }
  .tabs { display:flex; gap:10px; margin-top:10px; }
  .tab { padding:10px 14px; border:1px solid #1e2230; border-radius:10px; color:#c8cfda; cursor:pointer; background:#0e0f13; }
  .tab.active { border-color:#2a2f3f; background:#10121a; }
  .grid { display:grid; gap:16px; }
  .two { grid-template-columns: 1.1fr .9fr; }
  @media (max-width: 980px) { .two{ grid-template-columns: 1fr; } }
  .card { background: linear-gradient(180deg, #0f1015, #0b0c10); border:1px solid #1b1c22; border-radius:22px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
  .p { padding:16px; }
  .title { font-weight:900; font-size:1.1rem; margin:0 0 8px; }
  .muted { color: var(--muted); }
  .table { width:100%; border-collapse: collapse; margin-top:8px; }
  .table th, .table td { padding:10px 8px; border-bottom:1px solid #1b1c22; text-align:left; }
  input, select, textarea { width:100%; padding:12px 14px; border:1px solid #1d1f27; background:#0e0f13; color:var(--text); border-radius:12px; outline:none; }
  textarea { min-height:110px; resize:vertical; }
  .btn { padding:11px 14px; border-radius:12px; border:1px solid #262833; background:#0f1117; color:var(--text); cursor:pointer; }
  .primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); color:#0c0c12; font-weight:900; border:none; }
  .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
  .navlike { text-decoration:none; color:#b7c2d8; padding:8px 12px; border:1px solid #1e2230; border-radius:10px; }
  .navlike:hover { background:#0f1117; }
  .notice { padding:12px; border-radius:12px; background:rgba(0,212,255,.08); border:1px solid rgba(0,212,255,.25); margin:12px 0; display:none; }
  .ok { display:block; }
</style>
</head>
<body>
<header>
  <div class="wrap">
    <div class="brand"><div class="dot"></div> <div>StayFinder — Dashboard</div></div>
    <div class="actions" style="margin-top:10px;">
      <a class="navlike" href="javascript:void(0)" onclick="goto('index.php')">← Back to Listings</a>
    </div>
  </div>
</header>

<div class="wrap" style="padding:20px 16px 70px;">
  <?php if (!empty($added)): ?>
    <div class="notice ok">✅ Property added successfully!</div>
  <?php elseif (!empty($error)): ?>
    <div class="notice ok" style="background:rgba(255,86,86,.08);border-color:rgba(255,86,86,.35)">⚠️ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($ref==='booked' && $bid): ?>
    <div class="notice ok">✅ Booking #<?php echo htmlspecialchars($bid); ?> confirmed. You can review it below.</div>
  <?php endif; ?>

  <div class="tabs">
    <div class="tab active" data-tab="bookings">Bookings</div>
    <div class="tab" data-tab="properties">Properties</div>
    <div class="tab" data-tab="add">Add Property</div>
  </div>

  <div id="view-bookings" class="card" style="margin-top:14px;">
    <div class="p">
      <div class="title">Recent Bookings</div>
      <table class="table">
        <thead>
          <tr><th>#</th><th>Guest</th><th>Property</th><th>Dates</th><th>Guests</th><th>Total (SAR)</th></tr>
        </thead>
        <tbody>
          <?php if (!$bookings): ?>
          <tr><td colspan="6" class="muted">No bookings yet.</td></tr>
          <?php else: foreach ($bookings as $b): ?>
          <tr>
            <td><?php echo (int)$b['id']; ?></td>
            <td><?php echo htmlspecialchars($b['guest_name']); ?><br><span class="muted"><?php echo htmlspecialchars($b['guest_email']); ?></span></td>
            <td><?php echo htmlspecialchars($b['title']); ?></td>
            <td><?php echo htmlspecialchars($b['checkin'].' → '.$b['checkout']); ?></td>
            <td><?php echo (int)$b['guests']; ?></td>
            <td><?php echo number_format($b['total_price'],2); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="view-properties" class="card" style="margin-top:14px; display:none;">
    <div class="p">
      <div class="title">All Properties</div>
      <table class="table">
        <thead>
          <tr><th>#</th><th>Title</th><th>Location</th><th>Type</th><th>Price/night</th><th>Rating</th></tr>
        </thead>
        <tbody>
          <?php foreach ($props as $p): ?>
          <tr>
            <td><?php echo (int)$p['id']; ?></td>
            <td><?php echo htmlspecialchars($p['title']); ?></td>
            <td><?php echo htmlspecialchars($p['location']); ?></td>
            <td><?php echo htmlspecialchars($p['type']); ?></td>
            <td><?php echo number_format($p['price_per_night']); ?></td>
            <td><?php echo number_format($p['rating'],2); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="actions"><button class="btn" onclick="goto('index.php')">Open Listings</button></div>
    </div>
  </div>

  <div id="view-add" class="card" style="margin-top:14px; display:none;">
    <div class="p">
      <div class="title">Add a New Property</div>
      <form method="post" onsubmit="return validateAdd()">
        <input type="hidden" name="__add_property" value="1">
        <div class="grid two">
          <div>
            <label class="muted">Title</label>
            <input name="title" placeholder="e.g., Cozy Garden Suite">
          </div>
          <div>
            <label class="muted">Location</label>
            <input name="location" placeholder="City">
          </div>
        </div>
        <div class="grid two" style="margin-top:10px;">
          <div>
            <label class="muted">Price per night (SAR)</label>
            <input name="price" type="number" min="1" step="1" placeholder="300">
          </div>
          <div>
            <label class="muted">Type</label>
            <select name="type">
              <option>Apartment</option>
              <option>House</option>
              <option>Villa</option>
              <option>Unique stay</option>
            </select>
          </div>
        </div>
        <div class="grid two" style="margin-top:10px;">
          <div>
            <label class="muted">Amenities (comma separated)</label>
            <input name="amenities" placeholder="WiFi,AC,Kitchen">
          </div>
          <div>
            <label class="muted">Rating</label>
            <input name="rating" type="number" min="0" max="5" step="0.01" placeholder="4.8">
          </div>
        </div>
        <div style="margin-top:10px;">
          <label class="muted">Image URL</label>
          <input name="image" placeholder="https://...">
        </div>
        <div style="margin-top:10px;">
          <label class="muted">Description</label>
          <textarea name="description" placeholder="Short, attractive description..."></textarea>
        </div>
        <div class="actions">
          <button class="btn" type="button" onclick="goto('index.php')">Cancel</button>
          <button class="primary" type="submit">Save Property</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// JS-only navigation / tabs (no PHP redirects)
function goto(path){ location.href = path; }

document.querySelectorAll('.tab').forEach(t=>{
  t.addEventListener('click', ()=>{
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    t.classList.add('active');
    const tab = t.getAttribute('data-tab');
    document.getElementById('view-bookings').style.display = tab==='bookings'?'block':'none';
    document.getElementById('view-properties').style.display = tab==='properties'?'block':'none';
    document.getElementById('view-add').style.display = tab==='add'?'block':'none';
  });
});

// Basic validation for Add Property
function validateAdd(){
  const req = Array.from(document.querySelectorAll('input[name=title],input[name=location],input[name=price],input[name=amenities],input[name=image],textarea[name=description]'));
  for (const el of req){ if(!el.value.trim()){ alert('Please fill all fields.'); el.focus(); return false; } }
  return true;
}

// If landing from a booking, auto-open Bookings tab
<?php if ($ref==='booked'): ?>
  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelector('.tab[data-tab="bookings"]').click();
  });
<?php endif; ?>
</script>
</body>
</html>
