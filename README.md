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

## Usage

Start the development server:

```bash
php -S localhost:8000 -t src
```

## Testing

Run the included test script to verify functionality:

```bash
bash test.sh
```

The test script:

1. Creates a test file (`test.txt`)
2. Calculates SHA-256 hash
3. Generates CMS signature
4. Saves signature to `signature.cms`
5. Verifies signature using OpenSSL
