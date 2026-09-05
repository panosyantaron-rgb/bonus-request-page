# Bonus Request Project - Automated Setup Script for PowerShell
# Usage: .\setup.ps1

Write-Host ""
Write-Host "===============================================" -ForegroundColor Cyan
Write-Host "Bonus Request Project Setup" -ForegroundColor Cyan
Write-Host "===============================================" -ForegroundColor Cyan
Write-Host ""

# Check if we're in the correct directory
if (-not (Test-Path "index.html")) {
    Write-Host "Error: index.html not found. Please run this script from the project root directory." -ForegroundColor Red
    exit 1
}

# Check Git installation
Write-Host "[1/5] Checking Git installation..." -ForegroundColor Yellow
try {
    git --version | Out-Null
    Write-Host "✓ Git is installed" -ForegroundColor Green
} catch {
    Write-Host "Error: Git is not installed. Please install Git from https://git-scm.com/" -ForegroundColor Red
    exit 1
}

# Initialize Git
Write-Host ""
Write-Host "[2/5] Initializing Git repository..." -ForegroundColor Yellow
if (Test-Path ".git") {
    Write-Host "✓ Git repository already initialized" -ForegroundColor Green
} else {
    git init
    Write-Host "✓ Git repository initialized" -ForegroundColor Green
}

# Configure Git user
Write-Host ""
Write-Host "[3/5] Configuring Git user..." -ForegroundColor Yellow
$gitName = Read-Host "Enter your Git name (press Enter to skip)"
$gitEmail = Read-Host "Enter your Git email (press Enter to skip)"

if ($gitName -ne "") {
    git config user.name $gitName
    Write-Host "✓ Git user name set to: $gitName" -ForegroundColor Green
} else {
    Write-Host "- Skipping Git name configuration" -ForegroundColor Gray
}

if ($gitEmail -ne "") {
    git config user.email $gitEmail
    Write-Host "✓ Git email set to: $gitEmail" -ForegroundColor Green
} else {
    Write-Host "- Skipping Git email configuration" -ForegroundColor Gray
}

# Add files
Write-Host ""
Write-Host "[4/5] Adding files to Git..." -ForegroundColor Yellow
git add .
Write-Host "✓ Files staged" -ForegroundColor Green

# Initial commit
Write-Host ""
Write-Host "[5/5] Creating initial commit..." -ForegroundColor Yellow
$commitOutput = git commit -m "Initial commit: Add bonus request application with admin panel" 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Initial commit created" -ForegroundColor Green
} else {
    Write-Host "ℹ No changes to commit (repository may already be initialized)" -ForegroundColor Gray
}

Write-Host ""
Write-Host "===============================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Cyan
Write-Host "===============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Create a new repository on GitHub: https://github.com/new"
Write-Host "   - Repository name: bonus-request-page"
Write-Host "   - Do NOT initialize with README, .gitignore, or license"
Write-Host ""
Write-Host "2. Run these commands to connect to GitHub:"
Write-Host "   git remote add origin https://github.com/YOUR_USERNAME/bonus-request-page.git"
Write-Host "   git branch -M main"
Write-Host "   git push -u origin main"
Write-Host ""
Write-Host "3. Install Claude extensions:"
Write-Host "   - VS Code: Install 'Claude' by Anthropic from Extensions"
Write-Host "   - CLI: npm install -g @anthropic-ai/claude-code"
Write-Host ""
Write-Host "4. Open this folder in VS Code:"
Write-Host "   code ."
Write-Host ""
Read-Host "Press Enter to exit"
