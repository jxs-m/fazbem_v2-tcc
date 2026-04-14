function mostrarPreview(input) {
            const preview = document.getElementById('preview');
            if (input.files && input.files[0]) {
                preview.src = URL.createObjectURL(input.files[0]);
                preview.style.display = 'block';
            }
        }


        async function enviarProduto() {
            const form = document.getElementById('formUpload');
            const msg = document.getElementById('mensagem');


            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            msg.style.color = '#374151';
            msg.innerText = 'Enviando para o servidor...';

            try {

                const formData = new FormData(form);

                const response = await fetch('api_admin_produtos_v2.php', {
                    method: 'POST',
                    body: formData
                });

                const json = await response.json();

                if (json.success) {
                    msg.style.color = '#15803d';
                    msg.innerText = '✅ Produto cadastrado com sucesso!';
                    form.reset();
                    document.getElementById('preview').style.display = 'none';
                } else {
                    msg.style.color = '#dc2626';
                    msg.innerText = '❌ Erro: ' + json.message;
                }
            } catch (error) {
                msg.style.color = '#dc2626';
                msg.innerText = '❌ Erro de conexão com a API.';
            }
        }