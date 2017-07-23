#!/bin/bash

# Get ApiGen.phar
wget http://www.apigen.org/apigen.phar

# Get the boostrap theme
git clone https://github.com/jimmyz/ThemeBootstrap.git ../ApiGenTheme

# Generate docs
php apigen.phar generate -s src -s app -d ../docs --access-levels="public" --title="GeniBase" --template-config="../ApiGenTheme/src/config.neon"

# Set identity
git config --global user.email "travis@travis-ci.org"
git config --global user.name "Travis"

# Switch to gh-pages
git clone --branch gh-pages "https://${GH_TOKEN}@${GH_REF}" ../gh-pages > /dev/null
cd ../gh-pages

# Delete old docs and copy in new docs
rm -rf ./*
cp -R ../docs/* ./

# Push generated files
git add .
git commit -m "Update docs"
git push origin gh-pages -q > /dev/null
