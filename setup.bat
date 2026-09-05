@echo off
REM Bonus Request Project - Automated Setup Script for Windows
REM This script sets up the project directory and initializes Git

setlocal enabledelayedexpansion

echo.
echo ===============================================
echo Bonus Request Project Setup
echo ===============================================
echo.

REM Check if we're in the correct directory
if not exist "index.html" (
    echo Error: index.html not found. Please run this script from the project root directory.
    exit /b 1
)

echo [1/5] Checking Git installation...
git --version >nul 2>&1
if errorlevel 1 (
    echo Error: Git is not installed. Please install Git from https://git-scm.com/
    exit /b 1
)
echo ✓ Git is installed

echo.
echo [2/5] Initializing Git repository...
if exist ".git" (
    echo ✓ Git repository already initialized
) else (
    git init
    echo ✓ Git repository initialized
)

echo.
echo [3/5] Configuring Git user...
REM Get user input for name and email
set /p GIT_NAME="Enter your Git name (press Enter to skip): "
set /p GIT_EMAIL="Enter your Git email (press Enter to skip): "

if not "!GIT_NAME!"=="" (
    git config user.name "!GIT_NAME!"
    echo ✓ Git user name set to: !GIT_NAME!
) else (
    echo - Skipping Git name configuration
)

if not "!GIT_EMAIL!"=="" (
    git config user.email "!GIT_EMAIL!"
    echo ✓ Git email set to: !GIT_EMAIL!
) else (
    echo - Skipping Git email configuration
)

echo.
echo [4/5] Adding files to Git...
git add .
echo ✓ Files staged

echo.
echo [5/5] Creating initial commit...
git commit -m "Initial commit: Add bonus request application with admin panel" 2>nul
if errorlevel 1 (
    echo ℹ No changes to commit (repository may already be initialized)
) else (
    echo ✓ Initial commit created
)

echo.
echo ===============================================
echo Setup Complete!
echo ===============================================
echo.
echo Next steps:
echo 1. Create a new repository on GitHub: https://github.com/new
echo    - Repository name: bonus-request-page
echo    - Do NOT initialize with README, .gitignore, or license
echo.
echo 2. Run this command to connect to GitHub:
echo    git remote add origin https://github.com/YOUR_USERNAME/bonus-request-page.git
echo    git branch -M main
echo    git push -u origin main
echo.
echo 3. Install Claude extensions:
echo    - VS Code: Install "Claude" by Anthropic from Extensions
echo    - CLI: npm install -g @anthropic-ai/claude-code
echo.
echo 4. Open this folder in VS Code:
echo    code .
echo.
pause
