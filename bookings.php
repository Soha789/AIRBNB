<?php
// ---------- bookings.php ----------
function getDB() {
  $db = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $db;
}
$db = getDB();

// Handle AJAX booking request (no PHP redirect; JS decides navigation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action']==='reserve') {
  header('Content-Type: application/json');
  $payload = json_decode(file_get_contents('php://input'), true);
  if (!$payload) $payload = $_POST; // fallback
  $pid = (int)($payload['property_id'] ?? 0);
  $name = trim($payload['guest_name'] ?? '');
  $email = trim($payload['guest_email'] ?? '');
  $checkin = $payload['checkin'] ?? '';
  $checkout = $payload['checkout'] ?? '';
  $guests = (int)($payload['guests'] ?? 1);
  if (!$pid || !$name || !$email || !$checkin || !$checkout) {
    http_response_code(422);
    echo json_encode(['ok'=>false, 'error'=>'Missing required fields']); exit;
  }
  // Get price/night
  $price = (float)$db->prepare("SELECT price_per_night FROM properties WHERE id=?")
                     ->execute([$pid]) ? (float)$db->query("SELECT price_per_night FROM properties WHERE id=$pid")->fetchColumn() : 0;
  // Nights calc
  $n1 = strtotime($checkin); $n2 = strtotime($checkout);
  if (!$n1 || !$n2 || $n2 <= $n1) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Invalid dates']); exit; }
  $nights = ($n2 - $n1)/(60*60*24);
  $total = round($nights * $price, 2);

  $stmt = $db->prepare("INSERT INTO bookings (property_id, guest_name, guest_email, checkin, checkout, guests, total_price)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([$pid, $name, $email, $checkin, $checkout, $guests, $total]);
  $id = $db->lastInsertId();
  echo json_encode(['ok'=>true, 'booking_id'=>$id, 'total'=> $total]); exit;
}

// Load property for view
$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$prop = null;
if ($pid) {
  $s = $db->prepare("SELECT * FROM properties WHERE id=?");
  $s->execute([$pid]); $prop = $s->fetch(PDO::FETCH_ASSOC);
}
$ci = $_GET['checkin'] ?? '';
$co = $_GET['checkout'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Book Stay — StayFinder</title>
<style>
  :root { --bg:#0b0c10; --card:#111216; --muted:#a7b0c0; --text:#e9eef5; --accent:#7c5cff; --accent2:#00d4ff; }
  body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background: radial-gradient(1200px 600px at 80% -10%, rgba(124,92,255,.18), transparent), #0b0c10; color: var(--text); }
  header { position: sticky; top:0; z-index:10; backdrop-filter:saturate(140%) blur(10px); background: rgba(11,12,16,0.7); border-bottom: 1px solid #1b1c22; }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 18px 16px; }
  .brand { display:flex; align-items:center; gap:10px; font-weight:800; }
  .brand .dot { width:10px; height:10px; border-radius:999px; background: linear-gradient(135deg, var(--accent), var(--accent2)); box-shadow:0 0 18px var(--accent2); }
  .layout { max-width:1100px; margin: 22px auto 60px; padding:0 16px; display:grid; grid-template-columns: 1.3fr .9fr; gap:24px; }
  @media (max-width: 960px) { .layout { grid-template-columns: 1fr; } }
  .card { background: linear-gradient(180deg, #0f1015, #0b0c10); border:1px solid #1b1c22; border-radius:22px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
  .imgwrap { aspect-ratio: 16/9; overflow:hidden; }
  .imgwrap img { width:100%; height:100%; object-fit:cover; display:block; }
  .p { padding:16px; }
  .title { font-weight:900; font-size:1.25rem; margin:0 0 8px; }
  .muted { color: var(--muted); }
  .pill { display:inline-flex; gap:8px; padding:8px 12px; background:#0e0f13; border:1px solid #1d1f27; border-radius:999px; font-size:.9rem; margin-right:8px; margin-bottom:8px; }
  .box { background:#0e0f13; border:1px solid #1d1f27; border-radius:16px; padding:14px; }
  input, select { width:100%; padding:12px 14px; border:1px solid #1d1f27; background:#0e0f13; color:var(--text); border-radius:12px; outline:none; }
  .grid2 { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  .btn { width:100%; padding:12px 14px; border-radius:12px; border:1px solid #262833; background:#0f1117; color:var(--text); cursor:pointer; }
  .primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); color:#0c0c12; font-weight:900; border:none; }
  .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
  .navlike { text-decoration:none; color:#b7c2d8; padding:8px 12px; border:1px solid #1e2230; border-radius:10px; }
  .navlike:hover { background:#0f1117; }
  .price { font-size:1.15rem; font-weight:900; }
  .stars { color:#ffd166; font-weight:700; }
  .confirm { padding:14px; border-radius:14px; background:rgba(0,212,255,.08); border:1px solid rgba(0,212,255,.25); margin-top:12px; display:none; }
</style>
</head>
<body>
<header>
  <div class="wrap">
    <div class="brand"><div class="dot"></div> <div>StayFinder</div></div>
  </div>
</header>

<?php if (!$prop): ?>
  <div class="wrap" style="padding:30px 16px 60px;">
    <div class="box">
      <h2 style="margin:0 0 6px;">Property not found</h2>
      <p class="muted">Please go back and choose a listing.</p>
      <div class="actions"><a class="navlike" href="javascript:void(0)" onclick="location.href='index.php'">Back to Home</a></div>
    </div>
  </div>
<?php else: ?>
  <div class="layout">
    <div class="card">
      <div class="imgwrap"><img src="<?php echo htmlspecialchars($prop['image']); ?>" alt=""></div>
      <div class="p">
        <div class="title"><?php echo htmlspecialchars($prop['title']); ?></div>
        <div class="muted"><?php echo htmlspecialchars($prop['location']); ?> • <?php echo htmlspecialchars($prop['type']); ?> • <span class="stars">★ <?php echo number_format($prop['rating'],2); ?></span></div>
        <p class="muted" style="margin-top:10px"><?php echo htmlspecialchars($prop['description']); ?></p>
        <div style="margin-top:8px;">
          <?php foreach (explode(',', $prop['amenities']) as $a): ?>
            <span class="pill"><?php echo htmlspecialchars($a); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <div class="p">
          <div class="price">SAR <?php echo number_format($prop['price_per_night']); ?> <span class="muted">/night</span></div>
          <div class="grid2" style="margin-top:10px;">
            <div>
              <label class="muted">Check-in</label>
              <input type="date" id="checkin" value="<?php echo htmlspecialchars($ci); ?>">
            </div>
            <div>
              <label class="muted">Check-out</label>
              <input type="date" id="checkout" value="<?php echo htmlspecialchars($co); ?>">
            </div>
          </div>
          <div class="grid2" style="margin-top:10px;">
            <div>
              <label class="muted">Guests</label>
              <select id="guests">
                <?php for($i=1;$i<=8;$i++): ?>
                  <option value="<?php echo $i;?>"><?php echo $i;?> guest<?php echo $i>1?'s':'';?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div>
              <label class="muted">Price/night</label>
              <input type="text" value="SAR <?php echo number_format($prop['price_per_night']); ?>" disabled>
            </div>
          </div>
          <div class="box" style="margin-top:12px;">
            <div class="grid2">
              <div>
                <label class="muted">Full name</label>
                <input type="text" id="name" placeholder="Your name">
              </div>
              <div>
                <label class="muted">Email</label>
                <input type="email" id="email" placeholder="you@example.com">
              </div>
            </div>
            <button class="primary" style="margin-top:12px;" id="btnBook">Reserve now</button>
            <div class="confirm" id="confirmBox"></div>
          </div>

          <div class="actions">
            <a class="navlike" href="javascript:void(0)" onclick="goHome()">← Back</a>
            <a class="navlike" href="javascript:void(0)" onclick="gotoDashboard()">Dashboard</a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
function goHome(){ location.href = 'index.php'; }
function gotoDashboard(){ location.href = 'dashboard.php'; }

function nightsBetween(a,b){
  if(!a||!b) return 0;
  const d1=new Date(a), d2=new Date(b);
  return Math.max(0, Math.round((d2 - d1)/(1000*60*60*24)));
}

document.getElementById('btnBook')?.addEventListener('click', async () => {
  const checkin = document.getElementById('checkin').value;
  const checkout = document.getElementById('checkout').value;
  const name = document.getElementById('name').value.trim();
  const email = document.getElementById('email').value.trim();
  const guests = +document.getElementById('guests').value;
  if(!checkin || !checkout){ alert('Please select your dates.'); return; }
  if(new Date(checkout) <= new Date(checkin)){ alert('Checkout must be after checkin.'); return; }
  if(!name || !email){ alert('Please enter your name and email.'); return; }
  const property_id = <?php echo (int)$pid; ?>;

  const res = await fetch('bookings.php?action=reserve', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ property_id, guest_name:name, guest_email:email, checkin, checkout, guests })
  });
  const data = await res.json().catch(()=>({ok:false,error:'Bad response'}));
  const box = document.getElementById('confirmBox');
  if(!data.ok){
    box.style.display='block';
    box.style.borderColor = 'rgba(255,86,86,.35)';
    box.style.background = 'rgba(255,86,86,.08)';
    box.textContent = 'Booking failed: ' + (data.error || 'Unknown error');
    return;
  }
  const n = nightsBetween(checkin, checkout);
  box.style.display='block';
  box.textContent = `✅ Booking confirmed! #${data.booking_id} — ${n} night(s). Total: SAR ${data.total}`;
  // Redirect to dashboard via JS after a short pause (no PHP redirect)
  setTimeout(()=>{ location.href = 'dashboard.php?ref=booked&bid='+data.booking_id; }, 1200);
});
</script>
</body>
</html>
