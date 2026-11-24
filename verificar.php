<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$certificados = [
    '3223FJFF' => ['Ph.D. Abg. Maria Luisa Sotomayor Duarte', 'DERECHO'],
    '8K4M2QPX' => ['Ph.D. Abg. Edmundo Enrique Vilches Luzio', 'DERECHO'],
    '7T9L3WKA' => ['Msc. Sarai del Carmen Paredes Matos', 'ODONTOLOGÍA'],
    '5H8R2NVD' => ['Dr. Gabriel Galindo Velasquez', 'ODONTOLOGÍA'],
    '9C6J4ZMT' => ['Dra. Sonia Flores Mamani', 'MEDICINA'],
    '4P7X8BQS' => ['Dra. Alejandra Gabriela Noldin Paredes', 'MEDICINA'],
    '6V3F9YKE' => ['M.Sc. Jocelyn Brigitte Chavez Farro', 'ENFERMERÍA'],
];

$cod = isset($_GET['COD']) && is_string($_GET['COD']) ? strtoupper(trim($_GET['COD'])) : '';
$cert = $certificados[$cod] ?? null;
$consultado = array_key_exists('COD', $_GET);

function e(string $s): string
{
       return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>Verificación de certificados | UNIOR</title>
        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: system-ui,Arial,sans-serif;
                background: linear-gradient(135deg,#f4f4f5,#e5e7eb);
                color: #18181b;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
            }

            .card {
                width: 100%;
                max-width: 680px;
                background: white;
                border-radius: 22px;
                overflow: hidden;
                box-shadow: 0 20px 65px #0002;
                border-top: 7px solid #b91c1c;
            }

            header {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 24px;
                border-bottom: 1px solid #eee;
            }

            header img {
                width: 76px;
                height: 76px;
                object-fit: contain;
            }

            header strong {
                font-size: 19px;
            }

            small {
                color: #71717a;
                display: block;
                margin-top: 5px;
            }

            main {
                padding: 30px;
                text-align: center;
            }

            .seal {
                width: 82px;
                height: 82px;
                border-radius: 50%;
                background: #dcfce7;
                color: #15803d;
                display: grid;
                place-items: center;
                margin: 0 auto 18px;
                font-size: 48px;
                font-weight: bold;
            }

            .bad {
                background: #fee2e2;
                color: #b91c1c;
            }

            h1 {
                font-size: 24px;
                margin: 12px 0;
                color: #15803d;
            }

            h2 {
                font-size: 27px;
                line-height: 1.3;
                margin: 20px 0 10px;
            }

            .role {
                display: inline-block;
                background: #fef2f2;
                color: #991b1b;
                padding: 8px 14px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 700;
            }

            .details {
                text-align: left;
                background: #f8fafc;
                border: 1px solid #e5e7eb;
                border-radius: 14px;
                padding: 20px;
                margin: 24px 0;
            }

            .details div+div {
                border-top: 1px solid #e5e7eb;
                margin-top: 14px;
                padding-top: 14px;
            }

            .details b {
                display: block;
                margin-top: 5px;
            }

            form {
                display: flex;
                gap: 8px;
                margin-top: 20px;
            }

            input {
                min-width: 0;
                flex: 1;
                border: 1px solid #ccc;
                border-radius: 9px;
                padding: 12px;
                font-size: 16px;
                text-transform: uppercase;
            }

            button {
                border: 0;
                background: #b91c1c;
                color: white;
                padding: 12px 18px;
                border-radius: 9px;
                font-weight: bold;
                cursor: pointer;
            }

            footer {
                background: #18181b;
                color: #fff;
                text-align: center;
                padding: 20px;
                font-size: 13px;
            }

            footer small {
                color: #d4d4d8;
            }

            @media(max-width:480px) {
                body {
                    padding: 12px;
                }

                main {
                    padding: 20px;
                }

                header {
                    padding: 16px;
                }

                h2 {
                    font-size: 22px;
                }

                form {
                    flex-direction: column;
                }

            }

            @media print {
                body {
                    background: white;
                    padding: 0;
                }

                .card {
                    box-shadow: none;
                }

                form {
                    display: none;
                }

            }
        </style>
    </head>
    <body>
        <article class="card">
            <header>
                <img src="https://unior.softsit.cloud/cli.jpg" alt="Logo UNIOR">
                <div>
                    <strong>UNIVERSIDAD PRIVADA DE ORURO</strong>
                    <small>Portal de verificación de certificados</small>
                </div>
            </header>
            <main>
                <?php if ($cert): ?>
                    <div class="seal" aria-hidden="true">✓</div>
                    <h1>CERTIFICADO VERIFICADO</h1>
                    <p>El código QR corresponde al siguiente registro de certificado:</p>
                    <h2><?= e($cert[0]) ?></h2>
                    <span class="role">EXPOSITOR EXTRANJERO</span>
                    <div class="details">
                        <div>
                            <small>Evento académico</small>
                            <b>1.er Congreso Internacional de Integración Científica</b>
                        </div>
                        <div>
                            <small>Carrera</small>
                            <b><?= e($cert[1]) ?></b>
                        </div>
                        <div>
                            <small>Institución organizadora</small>
                            <b>Universidad Privada de Oruro — UNIOR</b>
                        </div>
                        <div>
                            <small>Fecha del evento</small>
                            <b>12 y 13 de noviembre de 2026</b>
                        </div>
                        <div>
                            <small>Lugar</small>
                            <b>Oruro, Bolivia</b>
                        </div>
                        <div>
                            <small>Código de verificación</small>
                            <b><?= e($cod) ?></b>
                        </div>
                    </div>
                <?php elseif ($consultado): ?>
                    <div class="seal bad" aria-hidden="true">×</div>
                    <h1 style="color:#b91c1c">CERTIFICADO NO ENCONTRADO</h1>
                    <p>El código ingresado no corresponde a ningún certificado registrado en este verificador.</p>
                <?php else: ?>
                    <h2>Verificar certificado</h2>
                    <p>Ingrese el código de verificación de su certificado académico.</p>
                <?php endif; ?>
                <form method="get">
                    <input name="COD" aria-label="Código de verificación" placeholder="Código de certificado" maxlength="64" required value="<?= e($cod) ?>">
                    <button type="submit">Verificar</button>
                </form>
            </main>
            <footer>Universidad Privada de Oruro — UNIOR<small>Verificación de certificados · 2026</small>
        </footer>
    </article>
</body>
</html>
