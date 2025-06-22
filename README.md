Simple PHP REST Client
A lightweight, browser-based REST client for making HTTP requests. This tool is built in a single PHP file, making it incredibly portable and easy to set up for testing API endpoints without the need for heavy desktop applications.

Features
  HTTP Methods: Supports GET and POST requests.
  Customizable Requests: Easily add custom headers, URL query parameters, and a request body.
  Smart Headers: Automatically adds the Content-Type: application/json header for POST requests with a JSON body.
  Detailed Response: View the HTTP status code, response time, response size, headers, and body.
  Syntax Highlighting: The JSON response body is automatically formatted and highlighted for readability.
  Request History: A session-based sidebar keeps track of your last 10 unique requests for easy re-testing.
  Responsive Design: A clean, modern, dark-mode interface that works on both desktop and mobile devices.

How to Use
  This is a server-side PHP application and requires a local server environment to run.

Prerequisites
  A local server environment like XAMPP, WAMP, or MAMP installed on your machine.
  The cURL extension for PHP must be enabled (it is enabled by default in most XAMPP installations).

Installation
  Download the index.php file from this repository.
  Place the file inside your web server's root directory. For XAMPP, this is the htdocs folder (e.g., C:/xampp/htdocs/rest-client/).

Running the Application
  Make sure your Apache server is running from your XAMPP control panel.
  Open your web browser and navigate to the project's URL. For example: http://localhost/rest-client/

Technologies Used
  Backend: PHP
  Frontend: HTML5, Tailwind CSS (via CDN), vanilla JavaScript
  HTTP Requests: PHP cURL extension
