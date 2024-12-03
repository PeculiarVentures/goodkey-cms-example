#!/bin/bash

# Create test data
echo "Hello, World!" >test.txt

# Calculate SHA-256 hash in hex format
HASH=$(openssl dgst -sha256 -binary test.txt | xxd -p -c 256)

# Send request to local server
curl -X POST \
  http://localhost:8000/index.php \
  -H 'Content-Type: application/json' \
  -d "{\"hash\":\"$HASH\"}" \
  --output signature.cms \
  --silent

# Check if signature file was created
if [ -f signature.cms ]; then
  # Verify CMS signature
  openssl smime -verify \
    -in signature.cms \
    -inform DER \
    -content test.txt \
    -noverify \
    >/dev/null 2>&1

  if [ $? -eq 0 ]; then
    echo "Signature verification successful"
  else
    echo "Signature verification failed"
  fi
else
  echo "Failed to create signature file"
fi
