const fs = require('fs');
const path = '/var/www/disputer/resources/views/livewire/user/cases/email-viewer.blade.php';
let content = fs.readFileSync(path, 'utf8');

// Revert px-6 to px-4 and px-5 to px-4 since px-6/px-5 might not be compiled in their Tailwind CSS
content = content.replace(/px-6 sm:px-8/g, 'px-4 sm:px-8');
content = content.replace(/px-6 sm:px-6/g, 'px-4 sm:px-6');
content = content.replace(/px-5 sm:px-6/g, 'px-4 sm:px-6');
content = content.replace(/py-5 bg-slate-50/g, 'py-4 sm:py-5 bg-slate-50');
content = content.replace(/p-6 sm:p-8/g, 'p-4 sm:p-6');

// Restore the user's padding to the main container, but make it symmetric
content = content.replace(
    /<div class="absolute sm:relative inset-0 sm:inset-auto bg-white sm:rounded-2xl shadow-2xl w-full h-full sm:h-\[90vh\] sm:max-w-4xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200 z-10">/,
    '<div class="absolute sm:relative inset-0 sm:inset-auto bg-white sm:rounded-2xl shadow-2xl w-full h-full sm:h-[90vh] sm:max-w-4xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200 z-10" style="padding: 0 1rem;">'
);

fs.writeFileSync(path, content);
console.log("Patched successfully!");
