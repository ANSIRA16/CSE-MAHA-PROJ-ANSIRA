# PHP

A minimal HTTP server written in PHP that exposes a friendly greeting and a `/ping` endpoint.

## What does this application do?

This application serves a simple web server that listens on defined port, default: `8000`.

# How to run?

You can run the application in one of the following ways:

1. Press `F5`. This will start the application in debug mode.

2. Open a terminal by going to 'View' -> 'Terminal'. Then run:
   > `php -S 0.0.0.0:8000 -t public`

This will start the application in development mode.

## Via curl command:

1. Open a terminal.
2. Type the following command:
   > `curl http://localhost:8000/api/ping`
3. Press 'Enter' to make the request.

Happy coding! 🙂
