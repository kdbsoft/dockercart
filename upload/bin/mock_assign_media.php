<?php
/**
 * Randomly assign 3D models, 360 images and videos to demo products.
 * Uses existing asset files only. Deterministic (seeded) so reruns give
 * the same distribution.
 *
 * Run inside the apache container: php bin/mock_assign_media.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

$dbHost = getenv('DB_HOSTNAME') ?: 'mariadb';
$dbUser = getenv('DB_USERNAME') ?: 'dockercart';
$dbPass = getenv('DB_PASSWORD') ?: 'dockercart_password';
$dbName = getenv('DB_DATABASE') ?: 'dockercart';
$prefix = getenv('DB_PREFIX') ?: 'oc_';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, 3306);

if ($mysqli->connect_errno) {
	fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
	exit(1);
}

$mysqli->set_charset('utf8mb4');

// ---------------------------------------------------------------------------
// Asset pools (files verified to exist)
// ---------------------------------------------------------------------------
$model3dFiles = array('catalog/Aero_Airship_01.glb');
$image360Files = array(
	'catalog/test_360_iphone.jpg',
	'catalog/test_360_chair.jpg',
	'catalog/test_360_lounge_chair.jpg',
	'catalog/test_360_cube.jpg',
	'catalog/demo/demo-seed/products/pet-supplies/furbo-360-dog-camera.jpg',
	'catalog/demo/demo-seed/products/pet-supplies/furbo-360-dog-camera-2.jpg',
);
$videoFile = 'catalog/demo/demo-seed/banners/0306.mp4';

// ---------------------------------------------------------------------------
// Product groups for thematic assignment
// ---------------------------------------------------------------------------
$smartphones = array(5001, 5002, 5003, 5004);
$laptops     = array(5005, 5006, 5007);
$audio       = array(5008, 5009, 5010);
$home        = array(5011, 5012, 5013, 5014, 5015, 5016, 5017, 5018, 5019, 5020);
$fashion     = array(5021, 5022, 5023, 5024, 5025, 5026, 5027, 5028, 5029, 5030);
$sports      = array(5031, 5032, 5033, 5034, 5035, 5036, 5037, 5038, 5039, 5040);
$beauty      = array(5041, 5042, 5043, 5044, 5045, 5046, 5047, 5048, 5049, 5050);
$games       = array(5051, 5052, 5053, 5054, 5055, 5056, 5057, 5058, 5059, 5060);
$books       = array(5061, 5062, 5063, 5064, 5065, 5066, 5067, 5068, 5069, 5070);
$auto        = array(5071, 5072, 5073, 5074, 5075, 5076, 5077, 5078, 5079, 5080);
$pets        = array(5081, 5082, 5083, 5085, 5086, 5087, 5088, 5089, 5090);

$all = array_merge($smartphones, $laptops, $audio, $home, $fashion, $sports, $beauty, $games, $books, $auto, $pets);

// ---------------------------------------------------------------------------
// Load products (skip discontinued)
// ---------------------------------------------------------------------------
$products = array();

$res = $mysqli->query("SELECT product_id, discontinued FROM {$prefix}product ORDER BY product_id");

while ($row = $res->fetch_assoc()) {
	if (!(int)$row['discontinued']) {
		$products[] = (int)$row['product_id'];
	}
}

// ---------------------------------------------------------------------------
// Clear previous media assignments (only for demo products)
// ---------------------------------------------------------------------------
$in = implode(',', $all);
$mysqli->query("UPDATE {$prefix}product SET model_3d = '', image_360 = '' WHERE product_id IN ({$in})");
$mysqli->query("DELETE FROM {$prefix}product_video WHERE product_id IN ({$in})");

// ---------------------------------------------------------------------------
// Deterministic RNG
// ---------------------------------------------------------------------------
mt_srand(20260810);

$stats = array('model_3d' => 0, 'image_360' => 0, 'video' => 0);

// --- 3D models: ~15% of electronics-ish products --------------------------
$model3dCandidates = array_merge($smartphones, $laptops, $audio, array(5031, 5038, 5051, 5052, 5053, 5071, 5078));

foreach ($model3dCandidates as $pid) {
	if (!in_array($pid, $products, true)) {
		continue;
	}

	if (mt_rand(1, 100) <= 30) { // 30% of candidates
		$mysqli->query("UPDATE {$prefix}product SET model_3d = '" . $mysqli->real_escape_string($model3dFiles[0]) . "' WHERE product_id = " . (int)$pid);
		$stats['model_3d']++;
	}
}

// --- 360 images: thematic --------------------------------------------------
$assign360 = array();

foreach ($smartphones as $pid) {
	if (in_array($pid, $products, true) && mt_rand(1, 100) <= 50) {
		$assign360[$pid] = 'catalog/test_360_iphone.jpg';
	}
}

foreach (array(5017, 5018, 5019, 5020, 5011, 5012) as $pid) { // home appliances
	if (in_array($pid, $products, true) && mt_rand(1, 100) <= 40) {
		$assign360[$pid] = 'catalog/test_360_lounge_chair.jpg';
	}
}

foreach (array(5005, 5006, 5007, 5035, 5036, 5040) as $pid) { // laptops/camping
	if (in_array($pid, $products, true) && mt_rand(1, 100) <= 35) {
		$assign360[$pid] = 'catalog/test_360_chair.jpg';
	}
}

foreach (array(5054, 5055, 5057, 5058, 5060, 5083) as $pid) { // toys
	if (in_array($pid, $products, true) && mt_rand(1, 100) <= 30) {
		$assign360[$pid] = 'catalog/test_360_cube.jpg';
	}
}

if (in_array(5081, $products, true)) { // Furbo dog camera
	$assign360[5081] = 'catalog/demo/demo-seed/products/pet-supplies/furbo-360-dog-camera.jpg';
	$stats['image_360']++;
}

foreach ($assign360 as $pid => $file) {
	$mysqli->query("UPDATE {$prefix}product SET image_360 = '" . $mysqli->real_escape_string($file) . "' WHERE product_id = " . (int)$pid);
	$stats['image_360']++;
}

// --- Videos: ~20% of electronics/home/sports/beauty ------------------------
$videoCandidates = array_merge($smartphones, $laptops, $audio, $home, $sports, array(5045, 5046, 5047, 5049, 5071, 5077, 5078));

foreach ($videoCandidates as $pid) {
	if (!in_array($pid, $products, true)) {
		continue;
	}

	if (mt_rand(1, 100) <= 22) {
		$mysqli->query(
			"INSERT INTO {$prefix}product_video (product_id, language_id, video_type, video, sort_order)
			 VALUES (" . (int)$pid . ", NULL, 'mp4', '" . $mysqli->real_escape_string($videoFile) . "', 0)"
		);
		$stats['video']++;
	}
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo 'products_processed: ' . count($products) . PHP_EOL;
echo 'model_3d_assigned: ' . $stats['model_3d'] . PHP_EOL;
echo 'image_360_assigned: ' . $stats['image_360'] . PHP_EOL;
echo 'video_assigned: ' . $stats['video'] . PHP_EOL;

// Products with any media
$res = $mysqli->query(
	"SELECT COUNT(DISTINCT product_id) AS cnt FROM (
		SELECT product_id FROM {$prefix}product WHERE model_3d != '' OR image_360 != ''
		UNION
		SELECT product_id FROM {$prefix}product_video
	) m"
);
echo 'products_with_any_media: ' . $res->fetch_assoc()['cnt'] . PHP_EOL;

$mysqli->close();
