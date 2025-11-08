<?php

class Product {
    public $id;
    public $isZeroWaste;
    public $description;
    public $category;
    public $subcategory; // Removed strict type for nullable property or add ?string

    public function __construct(string $id, bool $isZeroWaste, string $description, string $category, ?string $subcategory) {
        //                         ^ Add nullable type hint here
        $this->id = $id;
        $this->isZeroWaste = $isZeroWaste;
        $this->description = $description;
        $this->category = $category;
        $this->subcategory = $subcategory; // This will now accept null
    }
}