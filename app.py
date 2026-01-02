import subprocess
from flask import Flask, request, jsonify

# Initialize the Flask application
app = Flask(__name__)

@app.route('/process_payment', methods=['GET'])
def process_payment():
    """
    This endpoint accepts a credit card number via a query parameter,
    executes the backend PHP script, and returns the script's output.
    """
    # 1. Get the credit card number from the request's query parameters
    credit_card = request.args.get('cc')

    # 2. Validate the input
    if not credit_card:
        # Return a 400 Bad Request error if 'cc' is missing
        return jsonify({"error": "Credit card number 'cc' is required."}), 400
    
    # 3. Prepare the command to execute the PHP script
    # This assumes 'php' is in your system's PATH
    # and 'checkout_handler.php' is in the same directory as this Flask app.
    command = ['php', 'checkout_handler.php', credit_card]

    try:
        # 4. Execute the command
        # 'capture_output=True' captures stdout and stderr
        # 'text=True' decodes them as text
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            check=False # Do not raise an exception for non-zero exit codes
        )

        # 5. Check if the PHP script reported an error
        if result.returncode != 0:
            # If the script failed, return its error output
            return jsonify({
                "status": "error",
                "message": "PHP script execution failed.",
                "details": result.stderr.strip()
            }), 500

        # 6. If successful, return the script's standard output
        return jsonify({
            "status": "success",
            "output": result.stdout.strip()
        })

    except FileNotFoundError:
        # Handle case where 'php' command is not found
        return jsonify({"error": "PHP executable not found. Please ensure PHP is installed and in your system's PATH."}), 500
    except Exception as e:
        # Catch any other unexpected errors
        return jsonify({"error": f"An unexpected error occurred: {str(e)}"}), 500

if __name__ == '__main__':
    # Run the Flask app on host 0.0.0.0 (accessible from network) and port 5000
    app.run(host='0.0.0.0', port=5000, debug=True)
