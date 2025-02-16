# Desafio-Muper-Rastreamento

## Requisitos
- PHP 7.4+

Passos para rodar o desafio:

1 - Instalar as depedências com composer:
  composer install

2 - Iniciar o servidor:

  php -S 127.0.0.1:8080 -t public

3 - Enviar requisições POST para os endpoints:

- Para /index.php: envie o log em texto puro (modo raw, text/plain).
  A ideia aqui é que para cada linha do log, ele irá retornar um JSON com os dados.
  
- Para /grouped.php: o mesmo input, mas a resposta será um objeto JSON único com a chave "pacotes".
  A ideia aqui é agrupar as respostas de acordo com um pacote de log, separando eles por IMEIS diferentes.
