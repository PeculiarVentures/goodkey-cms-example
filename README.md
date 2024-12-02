# goodkey-cms-php

To set up the environment for running a PHP server and resolve the `command not found: php` error, you need to install PHP on your computer.

**For macOS:**

1. **Install Homebrew** (if it is not already installed):

   ```sh
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```

2. **Install PHP** using Homebrew:

   ```sh
   brew install php
   ```

3. **Start the server** from the directory with your `index.php`:

   ```sh
   php -S localhost:8000
   ```

**For Ubuntu/Debian:**

1. **Update the package manager**:

   ```sh
   sudo apt update
   ```

2. **Install PHP**:

   ```sh
   sudo apt install php
   ```

3. **Start the server** from the directory with your `index.php`:

   ```sh
   php -S localhost:8000
   ```

After installing PHP, you will be able to successfully start the server and use your API.
