<?php
/**
 * FileStorage Admin Layout
 *
 * Self-contained admin layout using Bootstrap 5 and Font Awesome 6 via CDN.
 * CSP-safe: all inline scripts use the request's `cspNonce` attribute.
 *
 * @var \Cake\View\View $this
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->fetch('title') ? strip_tags($this->fetch('title')) . ' - ' : '' ?>FileStorage Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --fs-primary: #0d6efd;
            --fs-success: #198754;
            --fs-warning: #ffc107;
            --fs-danger: #dc3545;
            --fs-info: #0dcaf0;
            --fs-secondary: #6c757d;
            --fs-dark: #212529;
            --fs-light: #f8f9fa;
            --fs-sidebar-bg: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            --fs-sidebar-width: 260px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f6f9;
            min-height: 100vh;
        }

        .fs-navbar {
            background: var(--fs-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .fs-navbar .navbar-brand { font-weight: 600; color: #fff; }
        .fs-navbar .navbar-brand i { color: var(--fs-primary); }

        .fs-sidebar {
            background: var(--fs-sidebar-bg);
            min-height: calc(100vh - 56px);
            width: var(--fs-sidebar-width);
            position: fixed;
            left: 0;
            top: 56px;
            padding: 1.5rem 0;
            overflow-y: auto;
        }
        .fs-sidebar .nav-section { padding: 0 1rem; margin-bottom: 1.5rem; }
        .fs-sidebar .nav-section-title {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 0.75rem;
            margin-bottom: 0.5rem;
        }
        .fs-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.6rem 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s ease;
        }
        .fs-sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .fs-sidebar .nav-link.active { color: #fff; background: var(--fs-primary); }
        .fs-sidebar .nav-link.disabled { color: rgba(255,255,255,0.35); cursor: not-allowed; }
        .fs-sidebar .nav-link i { width: 1.25rem; margin-right: 0.5rem; }

        .fs-mobile-nav-bg { background: var(--fs-sidebar-bg); }

        .fs-main {
            margin-left: var(--fs-sidebar-width);
            padding: 1.5rem;
            min-height: calc(100vh - 56px);
        }

        .stats-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }
        .stats-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stats-card .card-body { padding: 1.25rem; }
        .stats-card .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .stats-card .stats-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
        .stats-card .stats-label { color: var(--fs-secondary); font-size: 0.875rem; }

        .fs-table {
            background: #fff;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .fs-table thead th {
            background: var(--fs-light);
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            color: var(--fs-secondary);
        }
        .fs-table tbody tr:hover { background-color: rgba(13, 110, 253, 0.04); }

        .card { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-radius: 0.5rem; }
        .card-header { background: var(--fs-light); border-bottom: 1px solid #dee2e6; font-weight: 600; }

        .fs-footer {
            margin-left: var(--fs-sidebar-width);
            padding: 1rem 1.5rem;
            background: #fff;
            border-top: 1px solid #dee2e6;
            color: var(--fs-secondary);
            font-size: 0.875rem;
        }

        @media (max-width: 991.98px) {
            .fs-sidebar { position: relative; width: 100%; min-height: auto; top: 0; }
            .fs-main { margin-left: 0; }
            .fs-footer { margin-left: 0; }
        }
    </style>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fs-navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= $this->Url->build(['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorageDashboard', 'action' => 'index']) ?>">
                <i class="fas fa-folder-open me-2"></i>FileStorage Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <?php if (\Cake\Core\Plugin::isLoaded('Queue')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $this->Url->build(['plugin' => 'Queue', 'prefix' => 'Admin', 'controller' => 'Queue', 'action' => 'index']) ?>">
                            <i class="fas fa-layer-group me-1"></i>Queue
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <span class="nav-link text-light">
                            <i class="far fa-clock me-1"></i><?= date('Y-m-d H:i:s') ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="offcanvas offcanvas-start fs-mobile-nav-bg" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
        <div class="offcanvas-header border-bottom border-secondary">
            <h5 class="offcanvas-title text-white" id="mobileNavLabel">
                <i class="fas fa-folder-open me-2"></i>FileStorage Admin
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <?= $this->element('FileStorage.FileStorage/sidebar', ['mobile' => true]) ?>
        </div>
    </div>

    <div class="d-flex">
        <?= $this->element('FileStorage.FileStorage/sidebar') ?>

        <main class="fs-main flex-grow-1">
            <div class="fs-flash">
                <?= $this->element('FileStorage.flash/flash') ?>
            </div>

            <?= $this->fetch('content') ?>
        </main>
    </div>

    <footer class="fs-footer">
        <div class="d-flex justify-content-between align-items-center">
            <span>FileStorage Plugin for CakePHP</span>
            <span><i class="fas fa-server me-1"></i>PHP <?= phpversion() ?></span>
        </div>
    </footer>

    <?= $this->fetch('postLink') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <?php $cspNonce = (string)$this->getRequest()->getAttribute('cspNonce', ''); ?>
    <script<?= $cspNonce !== '' ? ' nonce="' . h($cspNonce) . '"' : '' ?>>
        document.addEventListener('DOMContentLoaded', function () {
            // Bootstrap tooltips
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            // Submit-confirm dialogs (CSP-safe: replaces inline confirm() bound via postLink)
            document.querySelectorAll('form[data-confirm-message]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (!confirm(this.dataset.confirmMessage)) {
                        e.preventDefault();
                    }
                });
            });

            // Bulk-action master checkbox
            document.querySelectorAll('[data-fs-checkall]').forEach(function (master) {
                master.addEventListener('change', function () {
                    var scope = document.querySelector(this.dataset.fsCheckall) || document;
                    scope.querySelectorAll('input[type=checkbox][name="ids[]"]').forEach(function (cb) {
                        cb.checked = master.checked;
                    });
                });
            });
        });
    </script>

    <?= $this->fetch('script') ?>
</body>
</html>
