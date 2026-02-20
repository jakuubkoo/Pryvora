#!/bin/bash
set -e

echo "🚀 Starting Pryvora Frontend Container..."

# Function to watch for package.json and start dev server
start_dev_server() {
    echo "📦 Installing dependencies..."
    npm install
    
    echo "🌐 Starting Vite dev server on 0.0.0.0:5173..."
    exec npm run dev -- --host 0.0.0.0 --port 5173
}

# Check if package.json exists
if [ -f "package.json" ]; then
    start_dev_server
else
    echo ""
    echo "⏳ No package.json found in ./frontend"
    echo ""
    echo "📝 To create your React + Vite project, run:"
    echo ""
    echo "   cd frontend"
    echo "   npm create vite@latest . -- --template react-swc"
    echo ""
    echo "🔄 This container will automatically detect the project and start the dev server."
    echo ""
    echo "👀 Watching for package.json..."
    
    # Watch for package.json creation
    while [ ! -f "package.json" ]; do
        sleep 2
    done
    
    echo ""
    echo "✅ package.json detected! Starting dev server..."
    start_dev_server
fi

