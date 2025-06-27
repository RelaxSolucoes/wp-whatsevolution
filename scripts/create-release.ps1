# Script PowerShell para criar release do WP WhatsEvolution
# Uso: .\scripts\create-release.ps1 [versão]

param(
    [Parameter(Mandatory=$true)]
    [string]$Version
)

# Função para exibir mensagens
function Write-Info {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

function Write-Header {
    Write-Host "================================" -ForegroundColor Blue
    Write-Host "  WP WhatsEvolution Release" -ForegroundColor Blue
    Write-Host "================================" -ForegroundColor Blue
}

$Tag = "v$Version"

Write-Header
Write-Info "Criando release para versão: $Version"

# Verifica se estamos no diretório correto
if (-not (Test-Path "wp-whatsapp-evolution.php")) {
    Write-Error "Execute este script no diretório raiz do plugin!"
    exit 1
}

# Verifica se o git está configurado
try {
    git rev-parse --git-dir | Out-Null
} catch {
    Write-Error "Este diretório não é um repositório git!"
    exit 1
}

# Verifica se há mudanças não commitadas
$status = git status --porcelain
if ($status) {
    Write-Warning "Há mudanças não commitadas no repositório!"
    $response = Read-Host "Deseja continuar mesmo assim? (y/N)"
    if ($response -notmatch "^[Yy]$") {
        Write-Info "Release cancelado."
        exit 1
    }
}

# Verifica se a tag já existe
$existingTags = git tag -l
if ($existingTags -contains $Tag) {
    Write-Error "A tag $Tag já existe!"
    exit 1
}

Write-Info "Criando tag: $Tag"

# Cria a tag
git tag -a $Tag -m "Release $Version"

Write-Info "Enviando tag para o repositório remoto..."

# Envia a tag
git push origin $Tag

Write-Info "✅ Release criado com sucesso!"
Write-Info "Tag: $Tag"
Write-Info "O GitHub Actions irá criar automaticamente o release com os assets."

# Pergunta se quer abrir o GitHub
Write-Host ""
$response = Read-Host "Deseja abrir o GitHub para verificar o release? (y/N)"
if ($response -match "^[Yy]$") {
    Write-Info "Abrindo GitHub..."
    Start-Process "https://github.com/RelaxSolucoes/wp-whatsevolution/releases"
}

Write-Info "🎉 Release processado com sucesso!" 