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
    public function getZeroWasteRecommendations(array $cart, int $limit = 3, int $minCoOccurrence = 2, bool $debug = false): array
    {
        $recommendationScores = [];
        $numTotalTransactions = count($this->transactions);

        if ($numTotalTransactions === 0) {
            return [];
        }

        if ($debug) {
            echo "<h4>Debugging Recommendation Process</h4>";
            echo "Total transactions: {$numTotalTransactions}<br><br>";
        }

        foreach ($cart as $cartItemId) {
            if (!isset($this->coOccurrence[$cartItemId])) {
                continue;
            }

            if ($debug) {
                echo "<strong>Cart Item:</strong> {$cartItemId}<br>";
            }

            foreach ($this->coOccurrence[$cartItemId] as $relatedProductId => $coOccurCount) {

                if ($coOccurCount < $minCoOccurrence) {
                    if ($debug) {
                        //echo "&nbsp;&nbsp;Skipping {$this->productsMeta[$relatedProductId]->description} due to low co-occurrence count ({$coOccurCount})<br><br>";
                    }
                    continue;
                }

                if (in_array($relatedProductId, $cart)) {
                    continue;
                }

                // Skip if product metadata not found
                if (!isset($this->productsMeta[$relatedProductId])) {
                    continue;
                }

                $relatedProduct = $this->productsMeta[$relatedProductId];

                // Only recommend zero-waste products
                if (!$relatedProduct->isZeroWaste) {
                    continue;
                }

                // Frequencies
                $frequencyCartItem = $this->productFrequencies[$cartItemId] ?? 0;
                $frequencyRelatedProduct = $this->productFrequencies[$relatedProductId] ?? 0;

                // Calculate Lift
                if ($frequencyCartItem === 0 || $frequencyRelatedProduct === 0) {
                    $lift = 0;
                } else {
                    $supportAB = $coOccurCount / $numTotalTransactions;
                    $supportA  = $frequencyCartItem / $numTotalTransactions;
                    $supportB  = $frequencyRelatedProduct / $numTotalTransactions;
                    $lift = ($supportA > 0 && $supportB > 0) ? $supportAB / ($supportA * $supportB) : 0;
                }

                // Accumulate
                $recommendationScores[$relatedProductId] = ($recommendationScores[$relatedProductId] ?? 0) + $lift;

                if ($debug) {
                    echo "&nbsp;&nbsp;Related Product: {$relatedProduct->description}<br>";
                    echo "&nbsp;&nbsp;Zero-Waste: " . ($relatedProduct->isZeroWaste ? "Yes" : "No") . "<br>";
                    echo "&nbsp;&nbsp;Co-Occurrence Count: {$coOccurCount}<br>";
                    echo "&nbsp;&nbsp;Total Frequency Of This Item: {$frequencyRelatedProduct}<br>";
                    echo "&nbsp;&nbsp;Lift: " . number_format($lift, 4) . "<br><br>";
                }
            }

            if ($debug) echo "<hr>";
        }

        // Sort and slice
        arsort($recommendationScores);
        $topRecommendationIds = array_slice(array_keys($recommendationScores), 0, $limit);

        $recommendedProducts = [];
        foreach ($topRecommendationIds as $productId) {
            $recommendedProducts[] = $this->productsMeta[$productId];
        }

        if ($debug) {
            echo "<h4>Top Recommendations (Zero-Waste Only)</h4>";
            foreach ($recommendedProducts as $p) {
                echo "{$p->description} — Score: " . number_format($recommendationScores[$p->id], 4) . "<br>";
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