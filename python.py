import json

def analyze_transactions_from_file(file_path):
    transaction_items = {}
    transaction_zerowaste_items = {}

    try:
        with open(file_path, 'r') as f:
            data = json.load(f)
    except FileNotFoundError:
        print(f"Error: The file '{file_path}' was not found.")
        return None
    except json.JSONDecodeError:
        print(f"Error: Could not decode JSON from '{file_path}'. Please ensure it's valid JSON.")
        return None

    # Assuming the structure is {"lines": [...]}
    if "lines" not in data or not isinstance(data["lines"], list):
        print("Error: The JSON data does not contain a 'lines' array as expected.")
        return None

    for line in data["lines"]:
        # Basic validation for expected keys in each line
        if not all(key in line for key in ["transaction", "zerowaste"]):
            print(f"Warning: Skipping line due to missing 'transaction' or 'zerowaste' key: {line}")
            continue

        transaction_id = line["transaction"]

        # Count total items per transaction
        if transaction_id not in transaction_items:
            transaction_items[transaction_id] = 0
        transaction_items[transaction_id] += 1

        # Count zerowaste items per transaction
        if transaction_id not in transaction_zerowaste_items:
            transaction_zerowaste_items[transaction_id] = 0
        if line["zerowaste"]:
            transaction_zerowaste_items[transaction_id] += 1

    total_transactions = len(transaction_items)

    if total_transactions == 0:
        return {
            "average_items_per_transaction": 0,
            "average_zerowaste_items_per_transaction": 0
        }

    # Calculate average number of items per transaction
    total_items = sum(transaction_items.values())
    average_items_per_transaction = total_items / total_transactions

    # Calculate average number of zerowaste items per transaction
    total_zerowaste_items = sum(transaction_zerowaste_items.values())
    average_zerowaste_items_per_transaction = total_zerowaste_items / total_transactions

    return {
        "average_items_per_transaction": average_items_per_transaction,
        "average_zerowaste_items_per_transaction": average_zerowaste_items_per_transaction
    }

# --- This is the part you were missing to execute the function and print the results ---
if __name__ == "__main__":
    file_path = 'transactions.json'  # Make sure this matches your file name
    results = analyze_transactions_from_file(file_path)

    if results:
        print(json.dumps(results, indent=4))