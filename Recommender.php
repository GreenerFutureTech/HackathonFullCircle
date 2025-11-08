<?php
require_once 'Product.php';

class Recommender {
    private $transactions = []; // [transaction_id => [product_id, ...]]
    private $productsMeta = []; // [product_id => Product object]
    private $coOccurrence = []; // [product_id_A => [product_id_B => count]]
    private $productFrequencies = []; // [product_id => count of transactions it appears in] // Keep this for now, could be useful for other functions or future changes

    public function __construct(array $transactionLines) {
        $this->buildData($transactionLines);
        $this->buildCoOccurrenceMatrix();
    }

    private function buildData(array $lines): void {
        foreach ($lines as $line) {
            $t = $line['transaction'];
            $pId = $line['product_id'];
    
            // Initialize the transaction array if it doesn't exist
            if (!isset($this->transactions[$t])) {
                $this->transactions[$t] = [];
            }
    
            // Only add the product ID to the transaction if it's not already there
            if (!in_array($pId, $this->transactions[$t])) {
                $this->transactions[$t][] = $pId;
            }
    
            if (!isset($this->productsMeta[$pId])) {
                $this->productsMeta[$pId] = new Product(
                    $pId,
                    (bool)$line['zerowaste'],
                    $line['description'],
                    $line['category'],
                    $line['subcategory'] ?? ''
                );
            }
        }
    }

    public function getCoOccurrenceForProduct(string $productId): array {
        return $this->coOccurrence[$productId] ?? [];
    }

    private function buildCoOccurrenceMatrix(): void {
        // numTransactions is not strictly needed for coOccurrence matrix itself,
        // but it's used if you were to calculate Lift/Confidence directly here.
        // Keeping it for consistency if other parts of the class were to use it.
        $numTransactions = count($this->transactions); 

        foreach ($this->transactions as $productsInTransaction) {
            // $uniqueProducts is effectively $productsInTransaction due to buildData's logic
            $uniqueProducts = array_unique($productsInTransaction); 

            foreach ($uniqueProducts as $pId) {
                $this->productFrequencies[$pId] = ($this->productFrequencies[$pId] ?? 0) + 1;
            }

            foreach ($uniqueProducts as $p1Id) {
                foreach ($uniqueProducts as $p2Id) {
                    if ($p1Id !== $p2Id) {
                        $this->coOccurrence[$p1Id][$p2Id] = ($this->coOccurrence[$p1Id][$p2Id] ?? 0) + 1;
                    }
                }
            }
        }
    }

    /**
     * Recommends zero-waste products based on the items in the cart,
     * prioritizing items with the highest total co-occurrence counts
     * with all items currently in the cart.
     *
     * @param array $cart Product IDs currently in the cart.
     * @param int $limit The maximum number of recommendations to return.
     * @param bool $debug Whether to output debug information.
     * @return Product[] An array of recommended Product objects, sorted by total co-occurrence score.
     */
    public function getZeroWasteRecommendations(array $cart, int $limit = 3, bool $debug = false): array
    {
        $recommendationScores = []; // This will store [related_product_id => total_co_occurrence_count]
    
        if ($debug) {
            echo "<h4>Debugging Recommendation Process (Co-occurrence Count Based)</h4>";
            echo "Total transactions: " . count($this->transactions) . "<br><br>";
            echo "Current Cart Items: " . implode(', ', $cart) . "<br><br>";
        }
    
        foreach ($cart as $cartItemId) {
            // Ensure the cart item exists in our co-occurrence data
            if (!isset($this->coOccurrence[$cartItemId])) {
                if ($debug) {
                    echo "<strong>Cart Item:</strong> {$cartItemId} - No co-occurrence data found. Skipping.<br>";
                }
                continue;
            }
    
            if ($debug) {
                echo "<strong>Processing Cart Item:</strong> {$this->productsMeta[$cartItemId]->description} ({$cartItemId})<br>";
            }
    
            // Iterate through all products that co-occurred with this cart item
            foreach ($this->coOccurrence[$cartItemId] as $relatedProductId => $coOccurCount) {
                // 1. Skip if the related product is already in the cart
                if (in_array($relatedProductId, $cart)) {
                    continue;
                }
    
                // 2. Skip if product metadata not found (shouldn't happen if coOccurrence is built from valid product IDs)
                if (!isset($this->productsMeta[$relatedProductId])) {
                    if ($debug) {
                        echo "&nbsp;&nbsp;Related Product: {$relatedProductId} - Metadata not found. Skipping.<br>";
                    }
                    continue;
                }
    
                $relatedProduct = $this->productsMeta[$relatedProductId];
    
                // 3. Only recommend zero-waste products
                if (!$relatedProduct->isZeroWaste) {
                    if ($debug) {
                        echo "&nbsp;&nbsp;Related Product: {$relatedProduct->description} - Not zero-waste. Skipping.<br>";
                    }
                    continue;
                }
    
                // Accumulate the raw co-occurrence count
                // This is the core change: we're just summing up how many times
                // this related product appeared with *any* item in the cart.
                $recommendationScores[$relatedProductId] = ($recommendationScores[$relatedProductId] ?? 0) + $coOccurCount;
    
                if ($debug) {
                    echo "&nbsp;&nbsp;Related Product: {$relatedProduct->description}<br>";
                    echo "&nbsp;&nbsp;Zero-Waste: " . ($relatedProduct->isZeroWaste ? "Yes" : "No") . "<br>";
                    echo "&nbsp;&nbsp;Co-Occurrence Count with '{$this->productsMeta[$cartItemId]->description}': {$coOccurCount}<br>";
                    // No need for Freq(related) or Lift in this pure co-occurrence approach for scoring
                    echo "&nbsp;&nbsp;Current total score for '{$relatedProduct->description}': " . ($recommendationScores[$relatedProductId] ?? $coOccurCount) . "<br><br>";
                }
            }
            if ($debug) echo "<hr>";
        }
    
        // Sort recommendations by score (total co-occurrence count) in descending order
        arsort($recommendationScores);
    
        // Get the top N recommendations
        $topRecommendationIds = array_slice(array_keys($recommendationScores), 0, $limit);
    
        // Convert product IDs back to Product objects for display
        $recommendedProducts = [];
        foreach ($topRecommendationIds as $productId) {
            $recommendedProducts[] = $this->productsMeta[$productId];
        }
    
        if ($debug) {
            echo "<h4>Final Top Recommendations (Zero-Waste Only, by Total Co-Occurrence)</h4>";
            if (empty($recommendedProducts)) {
                echo "No recommendations found.<br>";
            } else {
                foreach ($recommendedProducts as $p) {
                    echo "{$p->description} (ID: {$p->id}) — Total Co-Occurrence Score: " . number_format($recommendationScores[$p->id], 0) . "<br>";
                }
            }
        }
    
        return $recommendedProducts;
    }

    /**
     * Get a product by its ID.
     * @param string $id
     * @return Product|null
     */
    public function getProduct(string $id): ?Product {
        return $this->productsMeta[$id] ?? null;
    }

    /**
     * Get all product metadata.
     * @return Product[]
     */
    public function getAllProducts(): array {
        return $this->productsMeta;
    }

    /**
     * Get all product frequencies (for debugging/inspection).
     * @return array
     */
    public function getProductFrequencies(): array {
        return $this->productFrequencies;
    }
}