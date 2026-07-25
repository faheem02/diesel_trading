<?php
require_once __DIR__ . '/config.php';
?>
                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; <?= date('Y') ?> Diesel Trading System</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script src="<?= $asset_path ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $asset_path ?>vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?= $asset_path ?>vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="<?= $asset_path ?>vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="<?= $asset_path ?>js/sb-admin-2.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth < 768) {
            document.querySelectorAll('.sidebar .collapse.show').forEach(function(el) {
                el.classList.remove('show');
            });
            document.querySelectorAll('.sidebar .nav-link[aria-expanded="true"]').forEach(function(el) {
                el.setAttribute('aria-expanded', 'false');
                el.classList.add('collapsed');
            });
        }
    });
    </script>
</body>
</html>
