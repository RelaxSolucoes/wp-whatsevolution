#!/bin/bash

# Script para criar release do WP WhatsEvolution
# Uso: ./scripts/create-release.sh [versão]

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para exibir mensagens
print_message() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}  WP WhatsEvolution Release${NC}"
    echo -e "${BLUE}================================${NC}"
}

# Verifica se a versão foi fornecida
if [ -z "$1" ]; then
    print_error "Versão não fornecida!"
    echo "Uso: $0 [versão]"
    echo "Exemplo: $0 1.3.1"
    exit 1
fi

VERSION=$1
TAG="v$VERSION"

print_header
print_message "Criando release para versão: $VERSION"

# Verifica se estamos no diretório correto
if [ ! -f "wp-whatsapp-evolution.php" ]; then
    print_error "Execute este script no diretório raiz do plugin!"
    exit 1
fi

# Verifica se o git está configurado
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    print_error "Este diretório não é um repositório git!"
    exit 1
fi

# Verifica se há mudanças não commitadas
if [ -n "$(git status --porcelain)" ]; then
    print_warning "Há mudanças não commitadas no repositório!"
    echo "Deseja continuar mesmo assim? (y/N)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        print_message "Release cancelado."
        exit 1
    fi
fi

# Verifica se a tag já existe
if git tag -l | grep -q "^$TAG$"; then
    print_error "A tag $TAG já existe!"
    exit 1
fi

print_message "Criando tag: $TAG"

# Cria a tag
git tag -a "$TAG" -m "Release $VERSION"

print_message "Enviando tag para o repositório remoto..."

# Envia a tag
git push origin "$TAG"

print_message "✅ Release criado com sucesso!"
print_message "Tag: $TAG"
print_message "O GitHub Actions irá criar automaticamente o release com os assets."

# Pergunta se quer abrir o GitHub
echo ""
echo "Deseja abrir o GitHub para verificar o release? (y/N)"
read -r response
if [[ "$response" =~ ^[Yy]$ ]]; then
    print_message "Abrindo GitHub..."
    open "https://github.com/RelaxSolucoes/wp-whatsevolution/releases"
fi

print_message "🎉 Release processado com sucesso!" 