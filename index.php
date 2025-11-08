<?php
session_start();

require_once 'Recommender.php';





$productBundles = [
    'eco_starter_kit' => [
        'name' => 'Environmentally Friendly Shower Care Bundle',
        'product_ids' => ['UX6E2', 'XBjsO', '87E4N'], // Replace with actual product IDs
        'description' => 'Zero-waste shower supplies!!',
        'image' => 'soap.jpg',
    ],
    'cleaning_essentials' => [
        'name' => 'Zero-Waste Cleaning Bundle',
        'product_ids' => ['rEa04', 'yqm3d', '952W6'], // Replace with actual product IDs
        'description' => 'Clean without harming the environment!',
        'image' => 'cleaning.jpg',
    ],
    'personal_care' => [
        'name' => 'Beans and Legumes Bundle',
        'product_ids' => ['Qr7iQ', 'vx11x', 'lOUDi', '9thuF', '7YodN'], // Replace with actual product IDs
        'description' => 'Feel good with healthy sustainable food!',
        'image' => 'beans.jpg',
    ],
];



// --- Initialize Cart in Session ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // Initialize an empty array for the cart if it doesn't exist
}

// Load fake transaction data
$transactionLines = json_decode(file_get_contents('transactions.json'), true)['lines'];

// Initialize the recommender system
$recommender = new Recommender($transactionLines);

// --- Handle Cart Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $productId = $_POST['product_id'] ?? null;
    $bundleId = $_POST['bundle_id'] ?? null;

    if ($action === 'add' && $productId && $recommender->getProduct($productId)) {
        if (!in_array($productId, $_SESSION['cart'])) {
            $_SESSION['cart'][] = $productId;
        }
    } elseif ($action === 'remove' && $productId) {
        $_SESSION['cart'] = array_values(array_diff($_SESSION['cart'], [$productId]));
    } elseif ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
    } elseif ($action === 'add_bundle' && $bundleId && isset($productBundles[$bundleId])) {
        foreach ($productBundles[$bundleId]['product_ids'] as $bundleProductId) {
            if (!in_array($bundleProductId, $_SESSION['cart'])) { // ✅ Prevent duplicates
                $_SESSION['cart'][] = $bundleProductId;
            }
        }
    }

    // Redirect to prevent resubmission
    header('Location: index.php');
    exit;
}

// Get the current cart from the session
$cartProductIds = $_SESSION['cart'];

// Get recommendations based on the current cart
$recommendations = $recommender->getZeroWasteRecommendations($cartProductIds, 3, 2, false); // Get top 3
if (count($recommendations) < 3) {
    $recommendations = $recommender->getZeroWasteRecommendations($cartProductIds, 3, 1, false); // Get top 3

}
// --- Generate Random Products for "Discover" Section ---
$allProducts = $recommender->getAllProducts();
$randomProducts = [];
$numRandomProducts = 3; // How many random products to show
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

// Calculate zero-waste count in cart
$zeroWasteCountInCart = 0;
foreach ($cartProductIds as $cId) {
    $product = $recommender->getProduct($cId);
    if ($product && $product->isZeroWaste) {
        $zeroWasteCountInCart++;
    }
}

// Calculate zero-waste percentage for discount
$totalCartItems = count($cartProductIds);
$zeroWastePercentage = 0;
if ($totalCartItems > 0) {
    $zeroWastePercentage = ($zeroWasteCountInCart / $totalCartItems) * 100;
}

$showDiscountMessage = ($zeroWastePercentage >= 75) && ($totalCartItems > 4);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Recommendations</title>
    <style>
    /* --- Global Page Styling --- */
    body {
        font-family: "Inter", "Arial", sans-serif;
        background-color: #f8faf9;
        color: #333;
        margin: 0;
        padding: 30px;
    }

    .container {
        max-width: 1100px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 14px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }

    h1, h2 {
        color: #2e7d32;
        font-weight: 600;
    }

    h1 {
        font-size: 2em;
        text-align: center;
        margin-bottom: 25px;
        border-bottom: 3px solid #e0f2e9;
        padding-bottom: 10px;
    }

    h2 {
        font-size: 1.4em;
        margin-top: 30px;
        margin-bottom: 15px;
        border-bottom: 2px solid #c8e6c9;
        padding-bottom: 5px;
    }

    /* --- Product Card Layout --- */
    .product-card {
        background: #fff; /* Default background for all product cards */
        border-radius: 16px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        padding: 18px;
        margin: 20px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        transition: all 0.2s ease;
        position: relative;
    }

    .recommended-product-card { /* NEW RULE FOR RECOMMENDED ITEMS */
        background-color: #f0fdf0; /* A very light green/mint color */
        border-color: #d0e8d0; /* Slightly greener border */
    }

    .product-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-3px);
    }

    .product-details {
        flex-grow: 1;
        margin-right: 20px;
        min-width: 220px;
    }

    .product-card h4 {
        color: #2e7d32;
        margin: 0 0 5px;
        font-size: 1.1em;
        font-weight: 600;
    }

    .product-card p {
        margin: 0 0 8px;
        font-size: 0.95em;
        color: #555;
    }

    .product-card strong {
        color: #1b5e20;
        font-weight: bold;
        font-size: 1em;
    }

    .bundle-image {
    width: 180px;
    height: 180px;
    object-fit: cover;
    border-top-left-radius: 16px;
    border-bottom-left-radius: 16px;
    margin-right: 20px;
}

    /* --- Zero Waste Badge --- */
    .zero-waste-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #e8f5e9;
        color: #2e7d32;
        font-size: 0.8em;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .zero-waste-badge::before {
        content: "🌿";
        font-size: 1em;
    }

    /* --- Buttons --- */
    button {
        border: none;
        border-radius: 8px;
        cursor: pointer;
        padding: 8px 14px;
        font-size: 0.9em;
        transition: background 0.3s ease, transform 0.2s ease;
    }

    .add-button {
        background-color: #43a047;
        color: white;
        font-weight: 500;
    }

    .add-button:hover {
        background-color: #2e7d32;
        transform: translateY(-1px);
    }

    .remove-button {
        background-color: #e53935;
        color: white;
        font-weight: 500;
    }

    .remove-button:hover {
        background-color: #c62828;
        transform: translateY(-1px);
    }

    /* --- Empty Cart Message --- */
    .empty-message {
        text-align: center;
        color: #777;
        font-style: italic;
        margin-top: 20px;
    }

    /* --- Lists --- */
    ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    ul li {
        background: #f1f8e9;
        border-left: 5px solid #66bb6a;
        margin-bottom: 10px;
        padding: 10px 15px;
        border-radius: 5px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* --- Responsive --- */
    @media (max-width: 600px) {
        .product-card {
            flex-direction: column;
            align-items: flex-start;
        }
        .product-details {
            margin-bottom: 10px;
        }
        .add-button, .remove-button {
            width: 100%;
        }
    }
</style>
</head>
<body>
<div class="container">
    <h1>Product Recommendation System</h1>

    <h2>Your Cart: <small>(<?= $zeroWasteCountInCart ?>/<?= $totalCartItems ?> zero-waste products)</small></h2>

        <?php if ($showDiscountMessage): ?>
            <p style="color: #28a745; font-weight: bold; text-align: center; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                Congratulations! You qualify for a 10% discount on your order!
            </p>
        <?php endif; ?>
    <?php if (empty($cartProductIds)): ?>
        <p class="empty-message">Your cart is empty. Add some items to get recommendations!</p>
    <?php else: ?>
        <ul>
        <?php foreach ($cartProductIds as $cId): ?>
            <?php $product = $recommender->getProduct($cId); ?>
            <?php if ($product): ?>
                    <li>
                        <div> <!-- Added a div to group description and zero-waste tag -->
                            <span><?= htmlspecialchars($product->description) ?></span>
                            <?php if ($product->isZeroWaste): ?>
                                <span style="color: #28a745; font-weight: bold; font-size: 0.8em; margin-left: 10px;">Zero-waste!</span>
                            <?php endif; ?>
                        </div>
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
        <form method="post" action="index.php" style="margin-top: 20px;">
            <button type="submit" name="action" value="clear_cart" class="remove-button" style="background-color: #f44336;">Clear Cart</button>
        </form>
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
            <div class='product-card recommended-product-card'>
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

    <!-- --- New Bundles Section --- -->
    <h2>Curated Product Bundles:</h2>
    <?php if (empty($productBundles)): ?>
        <p class="empty-message">No product bundles available at the moment.</p>
    <?php else: ?>
        <?php foreach ($productBundles as $bundleId => $bundle): ?>
            <div class='product-card' style="background-color: #f0fff0; border-color: #c0f0c0;">
            <?php if (!empty($bundle['image'])): ?>
                <img src="<?= htmlspecialchars($bundle['image']) ?>" alt="<?= htmlspecialchars($bundle['name']) ?>" class="bundle-image">
            <?php endif; ?>    
            <div class="product-details">
                    <h4><?= htmlspecialchars($bundle['name']) ?></h4>
                    <p><?= htmlspecialchars($bundle['description']) ?></p>
                    <p style="font-size: 0.9em; margin-top: 10px; margin-bottom: 5px; color: #666;">
                        <strong>Items in this bundle:</strong>
                        <ul style="list-style: disc; margin-left: 20px; padding: 0; background: none; border: none;">
                            <?php foreach ($bundle['product_ids'] as $bundleProductId): ?>
                                <?php $item = $recommender->getProduct($bundleProductId); ?>
                                <?php if ($item): ?>
                                    <li style="background: none; border: none; padding: 2px 0; margin-bottom: 2px;">
                                        <?= htmlspecialchars($item->description) ?>
                                        <?php if ($item->isZeroWaste): ?>
                                            <span style="color: #28a745; font-size: 0.75em; font-weight: bold; margin-left: 5px;">(Zero-waste!)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php else: ?>
                                    <li style="background: none; border: none; padding: 2px 0; margin-bottom: 2px;">
                                        <em>Unknown Product (ID: <?= htmlspecialchars($bundleProductId) ?>)</em>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </p>
                </div>
                <form method="post" action="index.php" style="margin: 0;">
                    <input type="hidden" name="bundle_id" value="<?= htmlspecialchars($bundleId) ?>">
                    <button type="submit" name="action" value="add_bundle" class="add-button" style="background-color: #17a2b8;">Add Bundle to Cart</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>