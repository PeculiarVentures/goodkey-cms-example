# GoodKey CMS PHP SDK

A PHP SDK for creating CMS (Cryptographic Message Syntax) packages using GoodKey service.

## Requirements

- PHP 7.4 or higher
- Composer
- OpenSSL

## Installation

### 1. Install PHP

**On macOS:**

```sh
# Install via Homebrew
brew install php
```

**On Ubuntu/Debian:**

```sh
# Install via apt
sudo apt update
sudo apt install php
```

### 2. Install Dependencies

```bash
composer install
```

## Configuration

Set required environment variables:

```bash
# GoodKey API URL
export API_URL="http://api.goodkey.pp.ua"
# GoodKey API Token
export API_TOKEN="gkt-01234567890abcdef"
```

### Obtaining the API Token

To obtain the API token from the GoodKey server, follow these steps:

1. Open GoodKey application at https://app.goodkey.pp.ua/
2. Navigate to your organization page
3. Go to `Access tokens` tab
4. Click `Create token` button
5. Fill in the required fields
6. In the `Allowed keys` field, select a key
   > **Note**: The key must be RSA 2048 format, as the PHP server only implements RSA 2048 + SHA256
7. In the `Allowed certificates` field, select the certificate associated with the key
8. Complete the creation by clicking `Create token`
9. Copy the token value and use it as your `API_TOKEN`

## Usage

Start the development server:

```bash
php -S localhost:8000 -t src
```

## Testing

Run the included test script to verify functionality:

```bash
bash test-cli.sh
```

The test script:

1. Creates a test file (`test.txt`)
2. Calculates SHA-256 hash
3. Generates CMS signature
4. Saves signature to `signature.cms`
5. Verifies signature using OpenSSL
