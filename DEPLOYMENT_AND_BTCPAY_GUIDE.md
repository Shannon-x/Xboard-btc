# Xboard Deployment and BTCPay Server Configuration Guide

This guide provides comprehensive instructions for deploying Xboard using Docker and configuring its integration with BTCPay Server for payment processing.

## Table of Contents

1.  [BTCPay Server Configuration](#1-btcpay-server-configuration)
    *   [Store Settings](#store-settings)
    *   [Webhook Configuration](#webhook-configuration)
2.  [Docker Deployment](#2-docker-deployment)
    *   [Prerequisites](#prerequisites)
    *   [Step 1: Clone the Repository](#step-1-clone-the-repository)
    *   [Step 2: Configure Environment Variables (`.env` file)](#step-2-configure-environment-variables-env-file)
        *   [Essential Configuration](#essential-configuration)
        *   [Database Configuration](#database-configuration)
        *   [Redis Configuration](#redis-configuration)
        *   [Admin Account (Optional)](#admin-account-optional)
    *   [Step 3: Build and Run Docker Containers](#step-3-build-and-run-docker-containers)
    *   [Step 4: First Run - Xboard Installation](#step-4-first-run---xboard-installation)
    *   [Step 5: Accessing Xboard and Admin Credentials](#step-5-accessing-xboard-and-admin-credentials)
    *   [Step 6: Post-Installation Recommendations](#step-6-post-installation-recommendations)
3.  [Managing Xboard Containers](#3-managing-xboard-containers)
    *   [Stopping Containers](#stopping-containers)
    *   [Starting Containers](#starting-containers)
    *   [Viewing Logs](#viewing-logs)
    *   [Updating Xboard](#updating-xboard)
4.  [Troubleshooting](#4-troubleshooting)

## 1. BTCPay Server Configuration

To integrate Xboard with BTCPay Server, you need to configure your BTCPay store and set up a webhook.

### Store Settings

In your BTCPay Server, navigate to **Stores -> (Your Store) -> Settings**.
The following fields are relevant for Xboard integration:

*   **Store ID (`BTCPAY_STORE_ID`)**: The unique identifier for your store in BTCPay Server. This is required in your Xboard `.env` file.
*   **Store Name**: The name of your store. (Not directly used by Xboard but good for identification).
*   **Website**: Your website URL (e.g., `https://your-xboard-domain.com`).
*   **Default currency**: The default currency for your store (e.g., USD).
*   **Allow anyone to create invoice**: Usually enabled for public-facing stores.
*   **Invoice expires if the full amount has not been paid after (minutes)**: Set an appropriate expiration time for invoices. Xboard typically handles order status based on webhook events.
*   **Payment invalid if transactions fails to confirm \_\_ minutes after invoice expiration**: Defines how long to wait for confirmation after an invoice expires.
*   **Consider the invoice settled when the payment has \_\_ confirmations**: The number of blockchain confirmations required for an invoice to be considered settled. Xboard monitors this via webhooks.

### Webhook Configuration

Webhooks are essential for BTCPay Server to notify Xboard about invoice status changes (e.g., paid, completed, expired).

1.  In your BTCPay Server, go to **Stores -> (Your Store) -> Settings -> Webhooks**.
2.  Click **Create Webhook**.
3.  **Payload URL (`BTCPAY_WEBHOOK_URL`)**: This is the URL Xboard provides for BTCPay to send notifications to.
    *   The URL format is: `https://your-xboard-domain.com/api/callback/btcpay/{UUID}`
    *   Replace `https://your-xboard-domain.com` with your actual Xboard application's URL (must match `APP_URL` in your `.env`).
    *   `{UUID}` is a placeholder for the unique payment ID generated by Xboard for each order. **BTCPay Server will dynamically replace `{UUID}` with the actual `orderId` (metadata) you send when creating an invoice.**
    *   **Important:** When configuring this in BTCPay Server, you might need to use a specific variable that BTCPay Server provides for this purpose if it doesn't automatically use `orderId` from metadata. However, Xboard expects the final segment of the URL to be the order's UUID. The Xboard system generates an invoice with metadata `{'orderId': 'YOUR_XBOARD_ORDER_UUID'}`. BTCPay's webhook templating should use this `orderId`. If BTCPay Server's UI has a "Insert placeholder" for `OrderId` (from invoice metadata), use that. Otherwise, ensure your system that *creates* the BTCPay invoice includes `orderId` in the metadata.
    *   The `BTCPAY_WEBHOOK_URL` in Xboard's `.env` file should be the base URL without the `{UUID}` part, e.g., `https://your-xboard-domain.com/api/callback/btcpay`. Xboard internally appends the necessary UUID when interacting with BTCPay.
4.  **Webhook Secret (`BTCPAY_WEBHOOK_SECRET`)**:
    *   Click on **"Advanced Settings"** in the webhook creation form.
    *   A secret string used to sign the webhook payloads. This allows Xboard to verify that the incoming request is genuinely from your BTCPay Server.
    *   Generate a strong, random string for this secret.
    *   Copy this secret and save it. You will need to set it as `BTCPAY_WEBHOOK_SECRET` in Xboard's `.env` file.
5.  **Events**:
    *   It's generally recommended to send all events or at least the following:
        *   `InvoiceReceivedPayment`
        *   `InvoicePaymentSettled` (if you wait for confirmations)
        *   `InvoiceProcessing` (paid in full)
        *   `InvoiceExpired`
        *   `InvoiceInvalid`
        *   `InvoiceSettled` (fully completed and confirmed)
    *   Xboard primarily listens for `InvoiceSettled` (or `InvoiceProcessing` if confirmations are not strictly waited for in Xboard logic) to confirm successful payment and `InvoiceExpired` or `InvoiceInvalid` for failed/timed-out payments.
6.  **Automatic redelivery**: Enable this if you want BTCPay Server to retry sending webhooks if Xboard is temporarily unavailable.

Save the webhook. Ensure it's enabled.

## 2. Docker Deployment

This section guides you through deploying Xboard using Docker and Docker Compose.

### Prerequisites

*   **Docker**: Install Docker Desktop (Windows, macOS) or Docker Engine (Linux). See [Docker's official website](https://www.docker.com/get-started).
*   **Docker Compose**: Usually included with Docker Desktop. For Linux, you might need to install it separately.
*   **Git**: To clone the Xboard repository.
*   **A Text Editor**: For creating and editing the `.env` file (e.g., VS Code, Sublime Text, Nano).
*   **Domain Name (Recommended)**: For accessing Xboard over the internet and for HTTPS.
*   **BTCPay Server Instance**: Either self-hosted or a third-party provider.

### Step 1: Clone the Repository

If you haven't already, clone the Xboard repository to your server or local machine:

```bash
git clone <repository_url>
cd <repository_directory> # e.g., cd xboard
```

### Step 2: Configure Environment Variables (`.env` file)

Xboard uses an `.env` file for its configuration. The `docker-compose.yml` file is set up to pass these environment variables to the application containers.

1.  **Copy the example `.env` file:**
    ```bash
    cp .env.example .env
    ```
2.  **Edit the `.env` file** with your preferred text editor and configure the following:

#### Essential Configuration

*   **`APP_NAME`**: The name of your application (e.g., "Xboard").
    ```env
    APP_NAME="Xboard"
    ```
*   **`APP_ENV`**: Set to `production` for live environments. For development, `local` or `development` can be used.
    ```env
    APP_ENV=production
    ```
*   **`APP_KEY`**: **CRITICAL!** This is a unique 32-character random string used for encryption.
    *   **If you have PHP and Composer installed locally**, you can generate it with:
        ```bash
        php artisan key:generate --show
        ```
        Copy the output (e.g., `base64:xxxxxxxx...`) and paste it into your `.env` file.
    *   **Alternatively, after the initial container build (see Step 3), you can generate it using Docker:**
        ```bash
        docker-compose run --rm web php artisan key:generate --show
        ```
        Copy the output and update your `.env` file. Then, you might need to restart the containers.
    ```env
    APP_KEY= # Paste your generated key here
    ```
*   **`APP_DEBUG`**: Set to `false` for production.
    ```env
    APP_DEBUG=false
    ```
*   **`APP_URL`**: **CRITICAL!** The full URL where your Xboard application will be accessible. This is used for generating links, webhooks, etc.
    *   Include `http://` or `https://`.
    *   **Example:** `https://yourdomain.com` or `http://localhost:7001` if running locally without a reverse proxy.
    ```env
    APP_URL=http://localhost:7001
    ```
*   **`APP_PORT`**: (Optional, for host port mapping) The host port that maps to the container's port 7001. Defaults to 7001 if not set.
    ```env
    APP_PORT=7001
    ```

#### Database Configuration

The `docker-compose.yml` is set up for SQLite by default, which the `xboard:install` script will create inside the container (persisted via a volume).

*   **Default (SQLite):**
    The installation script will create a SQLite database. The `DB_CONNECTION` should be `sqlite`. Other `DB_*` variables are generally ignored for SQLite when the database file is managed by the application.
    ```env
    DB_CONNECTION=sqlite
    # DB_HOST=127.0.0.1 (Typically not needed for SQLite default path)
    # DB_PORT=3306
    # DB_DATABASE=laravel
    # DB_USERNAME=root
    # DB_PASSWORD=
    ```
    The SQLite database will be created at `/www/.docker/.data/database.sqlite` inside the container, which is not directly mapped in the default `docker-compose.yml`. If you want to explicitly map it, you can add a volume like `- ./data/database.sqlite:/www/.docker/.data/database.sqlite` to the `web` service in `docker-compose.yml` and create `./data` directory. The entrypoint script attempts to create the lock file in `/www/storage/INSTALLED.lock` which is mapped to `./storage` on the host.

*   **Using MySQL/PostgreSQL (Optional):**
    If you prefer to use MySQL or PostgreSQL:
    1.  Uncomment and configure the `db` service in your `docker-compose.yml`.
    2.  Set the following in your `.env` file:
        ```env
        DB_CONNECTION=mysql # or pgsql
        DB_HOST=db          # This MUST match the service name in docker-compose.yml
        DB_PORT=3306        # Or 5432 for pgsql
        DB_DATABASE=xboard  # Your desired database name (matches docker-compose)
        DB_USERNAME=xboard  # Your desired username (matches docker-compose)
        DB_PASSWORD=your_strong_password # Your desired password (matches docker-compose)
        ```
    3.  Ensure the credentials in `.env` match those in the `db` service environment in `docker-compose.yml`.

#### Redis Configuration

The `docker-compose.yml` sets up a Redis container. The application needs to be configured to connect to it via TCP.

```env
REDIS_CLIENT=phpredis
REDIS_HOST=redis    # This MUST match the service name in docker-compose.yml
REDIS_PASSWORD=your_secure_redis_password # Set a strong password
REDIS_PORT=6379
```
*   **Important:** The `REDIS_PASSWORD` **must** match the `requirepass` value set in the `command` for the `redis` service in `docker-compose.yml`. If you change it in one place, change it in the other.

#### BTCPay Server Integration

Configure these variables with the details from your BTCPay Server setup (see Section 1).

```env
BTCPAY_HOST=https://your-btcpay-server.com
BTCPAY_STORE_ID=YourBtcPayStoreID
BTCPAY_API_KEY=YourBtcPayApiKey # Optional, if needed for specific API interactions beyond webhooks
BTCPAY_WEBHOOK_SECRET=YourGeneratedWebhookSecret
# BTCPAY_WEBHOOK_URL is constructed by the application using APP_URL.
# Example: APP_URL/api/callback/btcpay
```

#### Admin Account (Optional)

You can pre-configure the admin account details. If not provided, the `xboard:install` command (run on first launch) will prompt you or generate them.

```env
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=your_strong_admin_password
```
If these are set, the installation script will use them. Otherwise, you'll be prompted or it will generate credentials.

### Step 3: Build and Run Docker Containers

Once your `.env` file is configured:

1.  **Build the Docker image and start the services:**
    ```bash
    docker-compose up --build -d
    ```
    *   `--build`: Forces Docker Compose to build the image from your `Dockerfile`.
    *   `-d`: Runs the containers in detached mode (in the background).

2.  **Check if containers are running:**
    ```bash
    docker-compose ps
    ```
    You should see `web`, `horizon`, and `redis` services running.

### Step 4: First Run - Xboard Installation

The `docker-entrypoint.sh` script handles the initial setup:

*   It checks if a lock file (`/www/storage/INSTALLED.lock`) exists.
*   **On the very first run**, the lock file won't exist, and the script will:
    1.  Copy `.env.example` to `.env` if `.env` is missing (though you should have created it).
    2.  Generate `APP_KEY` if not present in `.env`.
    3.  Link storage: `php artisan storage:link`.
    4.  Run database migrations: `php artisan migrate --force`.
    5.  Run the Xboard installation command: `php artisan xboard:install`.
        *   This command will set up the database, configure site settings, and create the admin user.
        *   If `ADMIN_EMAIL` and `ADMIN_PASSWORD` were set in `.env`, it will use them.
        *   Otherwise, it will prompt for admin credentials or generate them. **Pay attention to the logs during this first run to capture these credentials if generated.**

*   **On subsequent runs**, the lock file will exist, and the script will skip the installation steps and directly start `supervisord`.

### Step 5: Accessing Xboard and Admin Credentials

1.  **View Logs for Admin Credentials:**
    If the admin credentials were not pre-set in `.env`, the `xboard:install` command will output them to the logs.
    ```bash
    docker-compose logs web
    ```
    Look for lines similar to:
    ```
    Admin Email: admin@example.com
    Admin Password: generated_password
    Admin panel: https://your-xboard-domain.com/admin
    ```
    **Save these credentials securely.**

2.  **Access Xboard:**
    Open your web browser and navigate to your `APP_URL` (e.g., `http://localhost:7001` or `https://yourdomain.com`).
    To access the admin panel, go to `APP_URL/admin`.

### Step 6: Post-Installation Recommendations

*   **Change Default Admin Password:** If a password was auto-generated or you used a temporary one, log in to the admin panel and change it immediately.
*   **Review Configuration:** Double-check all settings in the Xboard admin panel.
*   **Set up Backups:** Implement a backup strategy for your Docker volumes (`./storage`, `./public/uploads`, `redis_data`, and `mysql_data` if used) and your `.env` file.
*   **Configure HTTPS:** If you haven't already, set up a reverse proxy (like Nginx or Traefik) to enable HTTPS for your Xboard instance. This is crucial for security.

## 3. Managing Xboard Containers

### Stopping Containers

To stop the Xboard application and its services:

```bash
docker-compose down
```
This stops and removes the containers but preserves the volumes (like `storage`, `redis_data`) by default.

To stop without removing containers (less common for full stop):
```bash
docker-compose stop
```

### Starting Containers

If containers are stopped, start them with:

```bash
docker-compose up -d
```

### Viewing Logs

To view the logs for a specific service (e.g., `web`):

```bash
docker-compose logs web
docker-compose logs horizon
docker-compose logs redis
```
Add `-f` or `--follow` to stream logs live: `docker-compose logs -f web`.

### Updating Xboard

To update Xboard to a newer version:

1.  **Pull the latest code (if you cloned from Git):**
    ```bash
    git pull origin main # Or the branch you are using
    ```
2.  **Rebuild the Docker image and restart services:**
    ```bash
    docker-compose up --build -d
    ```
    This will use the updated code and Dockerfile to build a new image. The entrypoint script should handle any necessary migrations or updates if designed to do so, or you might need to run specific `php artisan` commands via `docker-compose exec`.

3.  **Run database migrations (if not handled by entrypoint automatically on update):**
    ```bash
    docker-compose exec web php artisan migrate --force
    ```

## 4. Troubleshooting

*   **Permission Errors:** If you encounter permission errors with volume mounts (especially `./storage` or `./public/uploads`), ensure the `www` user (UID 1000) inside the container has write access. The Dockerfile attempts to set this, but host filesystem permissions can sometimes interfere. `chown -R 1000:1000 ./storage` on the host might be needed (use with caution).
*   **`APP_KEY` not set:** Ensure `APP_KEY` is correctly set in `.env` and is a valid base64 encoded key.
*   **Redis Connection Issues:**
    *   Verify `REDIS_HOST` is `redis` and `REDIS_PASSWORD` matches the one in `docker-compose.yml` for the Redis service.
    *   Check Redis logs: `docker-compose logs redis`.
*   **Database Connection Issues:**
    *   If using MySQL/Postgres, ensure `DB_HOST` is the service name (e.g., `db`) and credentials match.
    *   Check database service logs: `docker-compose logs db`.
*   **Webhooks not working:**
    *   Ensure `APP_URL` is correct and publicly accessible if BTCPay is external.
    *   Verify the webhook URL and secret in BTCPay Server match your Xboard configuration.
    *   Check Xboard logs (`docker-compose logs web`) for any errors when a webhook is received.
*   **Installation loop:** If the installation script runs every time, it means the lock file `INSTALL_LOCK_FILE="/www/storage/INSTALLED.lock"` is not being created or persisted. Check permissions and volume mounts for `/www/storage`.

This guide should help you get Xboard deployed and configured. Refer to the official Xboard documentation for more specific application features.
