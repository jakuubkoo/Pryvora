#!/bin/bash
set -e

echo "🚀 Starting Pryvora Backend Bootstrap..."

# Check if Symfony project exists
if [ ! -f "composer.json" ]; then
    echo "📦 No Symfony project found. Creating new Symfony 8 skeleton..."

    # Create Symfony project in temp directory
    composer create-project symfony/skeleton /tmp/symfony_temp --no-interaction

    # Move all files including hidden ones to current directory
    shopt -s dotglob nullglob
    mv /tmp/symfony_temp/* . 2>/dev/null || true
    rm -rf /tmp/symfony_temp

    # Fix routing configuration issue IMMEDIATELY after project creation
    echo "🔧 Fixing routing configuration..."
    if [ -f "config/routes.yaml" ]; then
        cat > config/routes.yaml <<'EOF'
# config/routes.yaml
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute
EOF
    fi

    echo "📚 Installing required Symfony packages..."

    # Install packages one by one to avoid conflicts (ignore cache clear errors)
    composer require symfony/orm-pack --no-interaction || true
    composer require symfony/maker-bundle --dev --no-interaction || true
    composer require symfony/security-bundle --no-interaction || true
    composer require symfony/validator --no-interaction || true
    composer require symfony/serializer --no-interaction || true
    composer require symfony/http-client --no-interaction || true
    composer require symfony/monolog-bundle --no-interaction || true
    composer require symfony/messenger --no-interaction || true
    composer require symfony/debug-bundle --dev --no-interaction || true
    composer require symfony/web-profiler-bundle --dev --no-interaction || true
    composer require nelmio/cors-bundle --no-interaction || true

    echo "⚙️  Configuring CORS for SPA development..."

    # Create CORS config
    mkdir -p config/packages
    cat > config/packages/nelmio_cors.yaml <<'EOF'
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['*']
        allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization']
        expose_headers: ['Link']
        max_age: 3600
    paths:
        '^/': ~
EOF

    echo "✅ Symfony project initialized successfully!"
else
    echo "✅ Symfony project already exists. Checking configuration..."

    # Fix routing configuration if it exists and is wrong
    if [ -f "config/routes.yaml" ]; then
        echo "🔧 Fixing routing configuration..."
        cat > config/routes.yaml <<'EOF'
# config/routes.yaml
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute
EOF
    fi

    echo "📦 Installing dependencies..."
    composer install --no-interaction
fi

# Ensure var directory has correct permissions
mkdir -p var/cache var/log
chmod -R 777 var

echo "🌐 Starting Symfony development server on 0.0.0.0:8000..."
exec php -S 0.0.0.0:8000 -t public

