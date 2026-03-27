<!-- Sidebar -->
<div class="w-64 bg-gray-800 text-white fixed h-full">
    <div class="p-4 text-center text-lg font-bold flex items-center justify-center">
        Asset IT PT CKT
    </div>
    <nav class="mt-6">
        <a href="<?php require_once __DIR__ . '/app_url.php'; echo htmlspecialchars(app_abs_path('admin/index')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
            <i class="fas fa-cogs mr-0"></i> Assets IT
        </a>
        <a href="#" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
            <i class="fas fa-file-alt mr-2"></i> Reports
        </a>
        <a href="<?php echo htmlspecialchars(app_abs_path('ticket')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
            <i class="fas fa-ticket-alt mr-2"></i> Ticket
        </a>
        <a href="<?php echo htmlspecialchars(app_abs_path('logout')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout
        </a>
    </nav>
</div>
