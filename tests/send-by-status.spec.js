const { test, expect } = require('@playwright/test');

test.describe('Envio por Status', () => {
  const TEST_MESSAGE = `Teste automatizado em ${new Date().toISOString()}`;
  
  // Hook para fazer login antes de cada teste neste grupo
  test.beforeEach(async ({ page }) => {
    // Navega para a página de login
    await page.goto('/wp-login.php');

    // Preenche as credenciais
    await page.locator('#user_login').fill(process.env.WP_ADMIN_USER);
    await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD);

    // Clica no botão de login
    await page.locator('#wp-submit').click();

    // Espera o login ser concluído e o dashboard aparecer
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });

  test('deve salvar a mensagem de um status com sucesso via AJAX', async ({ page }) => {
    // Navega para a página do plugin
    await page.goto('/wp-admin/admin.php?page=wpwevo-settings&tab=send-by-status');

    // Encontra o bloco do status "Processando"
    const processingBlock = page.locator('.wpwevo-status-block[data-status="processing"]');
    await expect(processingBlock).toBeVisible();
    
    // Ativa o checkbox (caso esteja desativado)
    await processingBlock.locator('.wpwevo-enable-checkbox').check();
    
    // Preenche a área de texto com a mensagem de teste
    const textarea = processingBlock.locator('.wpwevo-message-textarea');
    await textarea.fill(TEST_MESSAGE);

    // Clica no botão de salvar principal
    await page.locator('#wpwevo-save-button').click();

    // Verifica se a mensagem de sucesso aparece
    const successMessage = page.locator('#wpwevo-ajax-status .message.success');
    await expect(successMessage).toBeVisible();
    await expect(successMessage).toContainText('Configurações salvas com sucesso!');
    
    // Recarrega a página para garantir que o valor foi salvo no banco de dados
    await page.reload();
    
    // Verifica se a área de texto ainda contém a mensagem de teste após recarregar
    const reloadedTextarea = page.locator('.wpwevo-status-block[data-status="processing"] .wpwevo-message-textarea');
    await expect(reloadedTextarea).toHaveValue(TEST_MESSAGE);
  });
}); 