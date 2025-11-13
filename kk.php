<?php
session_start();
if(!isset($_SESSION['user_id']))header("Location:home.php"),exit;
$uid=$_SESSION['user_id'];
$db=new mysqli("localhost","root","","rawfit");if($db->connect_error)die("DB err");
$q=$db->prepare("SELECT name FROM register WHERE id=?");$q->bind_param('i',$uid);$q->execute();$q->bind_result($name);$q->fetch();$q->close();$db->close();
$first=explode(" ",$name)[0]??'User';?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>RawFit - <?=$first?></title>
</head>
<body>
<p style="color:#aaa;margin-bottom:30px">Ready to crush your fitness goals today?</p>
<h2>Quick Actions</h2>
<div class="grid">
<div class="card" onclick="location='nutrition.php'">
<div class="icon"><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg></div>
<h3>Nutrition Calculator</h3>
<p style="color:#aaa;font-size:14px">Track calories, macros & goals</p>
<span style="color:#f60;font-weight:600">Get Started →</span>
</div>
<div class="card" onclick="location='trainer.php'">
<div class="icon"><svg viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
<h3>Select Trainer</h3>
<p style="color:#aaa;font-size:14px">Book expert fitness sessions</p>
<span style="color:#f60;font-weight:600">Browse Trainers →</span>
</div>
<div class="card" onclick="location='feed.php'">
<div class="icon"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div>
<h3>Watch Bitz</h3>
<p style="color:#aaa;font-size:14px">Explore community fitness videos</p>
<span style="color:#f60;font-weight:600">Browse Bitz →</span>
</div>
<div class="card" onclick="location='display_gym.php'">
<div class="icon"><svg viewBox="0 0 24 24" fill="none"><path d="M3 12h4l1.5-6 3 12 3-9 1.5 6h4"/><path d="M8 22v-6m8 6v-6"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg></div>
<h3>Find Gyms Nearby</h3>
<p style="color:#aaa;font-size:14px">Book top gyms instantly</p>
<span style="color:#f60;font-weight:600">Explore Now →</span>
</div>
<div class="card" onclick="location='upload_reel.php'">
<div class="icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="22 6 12 2 2 6"/><line x1="2" y1="6" x2="2" y2="14"/><line x1="22" y1="6" x2="22" y2="14"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
<h3>Upload Bitz Video</h3>
<p style="color:#aaa;font-size:14px">Share your fitness journey</p>
<span style="color:#f60;font-weight:600">Get Started →</span>
</div>
<div class="card" onclick="location='workout.php'">
<div class="icon"><svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
<h3>Workout Management</h3>
<p style="color:#aaa;font-size:14px">Plan & track your workouts</p>
</div>
</div>
</body></html>