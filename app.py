import subprocess
from flask import Flask, request, jsonify
import traceback

app = Flask(__name__)

@app.route('/process_payment', methods=['GET'])
def process_payment():
    """
    This endpoint accepts a credit card number via a query parameter,
    executes the backend PHP script, and returns the script's output.
    """
    credit_card = request.args.get('cc')

    if not credit_card:
        return jsonify({
            "status": "error",
            "message": "Credit card number 'cc' is required."
        }), 400
    
    # Basic validation for credit card number
    if not credit_card.isdigit() or len(credit_card) < 13 or len(credit_card) > 19:
        return jsonify({
            "status": "error",
            "message": "Invalid credit card number format."
        }), 400
    
    command = ['php', 'checkout_handler.php', credit_card]

    try:
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            timeout=60,  # Add timeout to prevent hanging
            check=False
        )

        # If PHP script returns non-zero exit code
        if result.returncode != 0:
            error_details = result.stderr.strip() if result.stderr else "No error details available"
            
            # Check for specific common errors
            if "curl_init" in error_details:
                error_message = "PHP cURL extension is not installed."
                solution = "Please install PHP cURL: sudo apt-get install php-curl"
            elif "php: not found" in error_details:
                error_message = "PHP is not installed or not in PATH."
                solution = "Please install PHP: sudo apt-get install php"
            else:
                error_message = "PHP script execution failed."
                solution = ""
            
            return jsonify({
                "status": "error",
                "message": error_message,
                "details": error_details,
                "solution": solution,
                "output": result.stdout.strip() if result.stdout else ""
            }), 500

        # If successful
        return jsonify({
            "status": "success",
            "message": "Payment processing completed.",
            "output": result.stdout.strip() if result.stdout else "No output"
        })

    except subprocess.TimeoutExpired:
        return jsonify({
            "status": "error",
            "message": "PHP script execution timed out (60 seconds).",
            "details": "The checkout process took too long to complete."
        }), 500
    except FileNotFoundError:
        return jsonify({
            "status": "error",
            "message": "PHP executable not found.",
            "solution": "Please ensure PHP is installed and in your system's PATH: sudo apt-get install php",
            "details": "Command 'php' could not be found."
        }), 500
    except Exception as e:
        return jsonify({
            "status": "error",
            "message": "An unexpected error occurred.",
            "details": str(e),
            "traceback": traceback.format_exc()
        }), 500

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint to verify PHP is available."""
    try:
        # Check if PHP is installed
        php_check = subprocess.run(
            ['php', '--version'],
            capture_output=True,
            text=True
        )
        
        php_available = php_check.returncode == 0
        php_version = php_check.stdout.split('\n')[0] if php_available else "Not available"
        
        # Check if cURL extension is available
        curl_check_script = "<?php echo function_exists('curl_init') ? 'cURL: Available' : 'cURL: Not Available'; ?>"
        curl_check = subprocess.run(
            ['php', '-r', curl_check_script],
            capture_output=True,
            text=True
        )
        
        return jsonify({
            "status": "healthy" if php_available else "unhealthy",
            "php_available": php_available,
            "php_version": php_version,
            "curl_status": curl_check.stdout.strip() if curl_check.returncode == 0 else "Check failed",
            "app": "Flask + PHP Payment Processor"
        })
    except Exception as e:
        return jsonify({
            "status": "error",
            "message": str(e)
        }), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)  # Set debug=False for production