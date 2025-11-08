<?php
session_start();

require_once 'Recommender.php';

// --- Initialize Cart in Session ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // Initialize an empty array for the cart if it doesn't exist
}

// Load fake transaction data
$transactionLines = json_decode(file_get_contents('transactions.json'), true)['lines'];

// Initialize the recommender system
$recommender = new Recommender($transactionLines);

// --- Handle Cart Actions (Add/Remove) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $productId = $_POST['product_id'] ?? null;

        if ($productId && $recommender->getProduct($productId)) { // Ensure product ID is valid
            switch ($_POST['action']) {
                case 'add':
                    if (!in_array($productId, $_SESSION['cart'])) {
                        $_SESSION['cart'][] = $productId; // Add to cart if not already present
                    }
                    break;
                case 'remove':
                    $_SESSION['cart'] = array_diff($_SESSION['cart'], [$productId]); // Remove from cart
                    // array_diff preserves keys, so re-index to keep it clean
                    $_SESSION['cart'] = array_values($_SESSION['cart']);
                    break;
            }
        }
    }
    // Redirect to prevent form resubmission on refresh
    header('Location: index.php');
    exit;
}

// Get the current cart from the session
$cartProductIds = $_SESSION['cart'];

// Get recommendations based on the current cart
$recommendations = $recommender->getZeroWasteRecommendations($cartProductIds, 3, true); // Get top 3

// --- Generate Random Products for "Discover" Section ---
$allProducts = $recommender->getAllProducts();
$randomProducts = [];
$numRandomProducts = 5; // How many random products to show
$productIdsInCart = array_values($cartProductIds); // Get values and re-index for easier checking

// Filter out products already in the cart from the random selection pool
$availableProductIds = array_keys(array_filter($allProducts, function($product) use ($productIdsInCart) {
    return !in_array($product->id, $productIdsInCart);
}));

if (count($availableProductIds) > 0) {
    // Shuffle and pick a few random unique product IDs
    shuffle($availableProductIds);
    $selectedRandomIds = array_slice($availableProductIds, 0, $numRandomProducts);

    foreach ($selectedRandomIds as $pId) {
        $randomProducts[] = $allProducts[$pId];
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Recommendations</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; line-height: 1.6; color: #333; background-color: #f4f7f6; }
        .container { max-width: 960px; margin: 20px auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1, h2 { color: #0056b3; border-bottom: 2px solid #e9f5ff; padding-bottom: 10px; margin-top: 25px; }
        h1 { font-size: 2.2em; text-align: center; margin-bottom: 30px; }
        h2 { font-size: 1.6em; }
        ul { list-style: none; padding: 0; margin: 0; }
        ul li { background: #e9f5ff; border-left: 5px solid #007bff; margin-bottom: 10px; padding: 10px 15px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .product-card { border: 1px solid #cce5ff; background: #e0f2ff; padding: 15px; margin: 15px 0; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .product-card h4 { margin-top: 0; margin-bottom: 5px; color: #0056b3; font-size: 1.1em; }
        .product-card p { margin: 0 0 5px 0; font-size: 0.9em; color: #555; }
        .product-card strong { color: #28a745; font-weight: bold; }
        button { padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 0.9em; transition: background 0.3s ease, transform 0.2s ease; border: none; }
        .add-button { background: #28a745; color: white; }
        .add-button:hover { background: #218838; transform: translateY(-1px); }
        .remove-button { background: #dc3545; color: white; }
        .remove-button:hover { background: #c82333; transform: translateY(-1px); }
        .product-details { flex-grow: 1; margin-right: 15px; }
        .empty-message { color: #6c757d; font-style: italic; padding: 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>Product Recommendation System</h1>

    <h2>Your Cart:</h2>
    <?php if (empty($cartProductIds)): ?>
        <p class="empty-message">Your cart is empty. Add some items to get recommendations!</p>
    <?php else: ?>
        <ul>
        <?php foreach ($cartProductIds as $cId): ?>
            <?php $product = $recommender->getProduct($cId); ?>
            <?php if ($product): ?>
                <li>
                    <span><?= htmlspecialchars($product->description) ?></span>
                    <form method="post" action="index.php" style="margin: 0;">
                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($cId) ?>">
                        <button type="submit" name="action" value="remove" class="remove-button">Remove</button>
                    </form>
                </li>
            <?php else: ?>
                <li>
                    <span>Unknown Product (ID: <?= htmlspecialchars($cId) ?>)</span>
                    <form method="post" action="index.php" style="margin: 0;">
                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($cId) ?>">
                        <button type="submit" name="action" value="remove" class="remove-button">Remove</button>
                    </form>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Discover New Products:</h2>
    <?php if (empty($randomProducts)): ?>
        <p class="empty-message">No more products to discover!</p>
    <?php else: ?>
        <?php foreach ($randomProducts as $product): ?>
            <div class='product-card'>
                <div class="product-details">
                    <h4><?= htmlspecialchars($product->description) ?></h4>
                    <p>Category: <?= htmlspecialchars($product->category) ?> &rarr; <?= htmlspecialchars($product->subcategory ?? 'N/A') ?></p>
                    <?php if ($product->isZeroWaste): ?>
                        <p><strong>Zero-waste!</strong></p>
                    <?php endif; ?>
                </div>
                <form method="post" action="index.php" style="margin: 0;">
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product->id) ?>">
                    <button type="submit" name="action" value="add" class="add-button">Add to Cart</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>


    <h2>Top <?= count($recommendations) > 0 ? count($recommendations) : '' ?> Recommended Zero-Waste Products:</h2>
    <?php if (empty($recommendations)): ?>
        <p class="empty-message">No zero-waste recommendations found based on your cart. Add some items from 'Discover New Products'!</p>
    <?php else: ?>
        <?php foreach ($recommendations as $product): ?>
            <div class='product-card'>
                <div class="product-details">
                    <h4><?= htmlspecialchars($product->description) ?></h4>
                    <p>Category: <?= htmlspecialchars($product->category) ?> &rarr; <?= htmlspecialchars($product->subcategory ?? 'N/A') ?></p>
                    <?php if ($product->isZeroWaste): ?>
                        <p><strong>Zero-waste!</strong></p>
                    <?php endif; ?>
                </div>
                <form method="post" action="index.php" style="margin: 0;">
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product->id) ?>">
                    <button type="submit" name="action" value="add" class="add-button">Add to Cart</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>