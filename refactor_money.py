import os
import re

files = [
    "resources/js/Pages/Invoices/Index.vue",
    "resources/js/Pages/Dashboard.vue",
    "resources/js/Pages/Payments/Contract.vue",
    "resources/js/Pages/Clients/Edit.vue",
    "resources/js/Pages/Clients/Show.vue",
    "resources/js/Pages/Clients/Index.vue",
    "resources/js/Pages/Payments/Index.vue",
    "resources/js/Pages/Clients/Create.vue",
    "resources/js/Pages/Contracts/Renewals.vue",
    "resources/js/Pages/Quotes/Create.vue",
    "resources/js/Pages/Quotes/Edit.vue",
    "resources/js/Pages/Contracts/Index.vue",
    "resources/js/Pages/Quotes/Index.vue",
    "resources/js/Pages/Finances/Index.vue",
    "resources/js/Pages/Contracts/Show.vue",
    "resources/js/Pages/Contracts/AdminShow.vue",
    "resources/js/Pages/Quotes/Wizard/StepPackage.vue",
    "resources/js/Pages/HR/PayrollPrint.vue",
    "resources/js/Pages/Quotes/Wizard/StepSummary.vue",
    "resources/js/Pages/Quotes/Manage.vue",
    "resources/js/Pages/Quotes/Wizard/StepAddons.vue",
    "resources/js/Pages/Services/Edit.vue",
    "resources/js/Pages/Services/Addons/Edit.vue",
    "resources/js/Pages/Services/Addons/Index.vue",
    "resources/js/Pages/Services/Addons/Create.vue",
    "resources/js/Pages/Services/Index.vue",
    "resources/js/Pages/Services/Create.vue",
]

def refactor_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    modified = False
    
    # Ensure import exists
    import_stmt = "import { useMoney } from '@/Composables/useMoney';"
    if import_stmt not in content:
        m = re.search(r'<script setup>', content)
        if m:
            content = content[:m.end()] + "\n" + import_stmt + content[m.end():]
            modified = True

    # 1. Single line: const name = (arg) => new Intl...format(...)
    # matches: const fmt = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
    # matches: const fmt      = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
    # matches: const fmtMoney = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n || 0));
    
    def repl_single(m):
        name = m.group(1)
        # Use a hidden name for destructor to avoid collision if name is 'fmt'
        sub_name = f"_{name}"
        return f"const {{ fmt: {sub_name} }} = useMoney();\nconst {name} = (n, c) => {sub_name}(n, c);"

    # Simplified single line regex
    re_single = r'const\s+(fmt|fmtMoney)\s*=\s*\([^\)]*\)\s*=>\s*new\s+Intl\.NumberFormat\(\s*\'es-MX\'[\s\S]*?\)\.format\([\s\S]*?\);'
    
    if re.search(re_single, content):
        content = re.sub(re_single, repl_single, content)
        modified = True

    # 2. Block/Function style: const name = (arg) => { [return] new Intl...format(...) }
    def repl_block(m):
        name = m.group(1)
        args = m.group(2)
        sub_name = f"_{name}"
        return f"const {{ fmt: {sub_name} }} = useMoney();\nconst {name} = ({args}, currency) => {sub_name}({args.split(',')[0].strip()}, currency);"

    re_block = r'const\s+(\w+)\s*=\s*\((.*?)\)\s*=>\s*\{\s*(?:return\s+)?new\s+Intl\.NumberFormat\(\s*\'es-MX\'[\s\S]*?\}\)\.format\((.*?)\);?\s*\}'
    
    if re.search(re_block, content):
        content = re.sub(re_block, repl_block, content)
        modified = True

    # 3. Special case for template only in Clients/Show.vue
    if "Clients/Show.vue" in filepath:
        if "const { fmt" not in content:
            m = re.search(re.escape(import_stmt), content)
            if m:
                content = content[:m.end()] + "\nconst { fmt } = useMoney();" + content[m.end():]
                modified = True
        
        old1 = "new Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN'}).format(Number(c.total_amount) || 0)"
        old2 = "new Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN'}).format(Number(c.monthly_amount) || 0)"
        if old1 in content:
            content = content.replace(old1, "fmt(c.total_amount, c.currency)")
            modified = True
        if old2 in content:
            content = content.replace(old2, "fmt(c.monthly_amount, c.currency)")
            modified = True

    # 4. Catch remaining loose returns in functions
    # Like in Dashboard.vue or Finances/Index.vue
    # e.g. return new Intl.NumberFormat('es-MX', { ... }).format(amount);
    # This is harder to replace safely without knowing the function name.
    # Let's try to match the whole function if possible or just the return.
    
    # Find all occurrences of the return pattern
    pattern_return = r'return\s+new\s+Intl\.NumberFormat\(\s*\'es-MX\'[\s\S]*?\}\)\.format\((.*?)\);'
    if re.search(pattern_return, content):
        # We'll just define an anonymous _fmt at the top level or within the function?
        # Better to have it at script level.
        if "const { fmt: _fmt } = useMoney();" not in content and "const { fmt }" not in content:
             m = re.search(re.escape(import_stmt), content)
             if m:
                 content = content[:m.end()] + "\nconst { fmt: _fmt } = useMoney();" + content[m.end():]
                 modified = True
        
        content = re.sub(pattern_return, r'return _fmt(\1);', content)
        modified = True

    # One more: Services/Addons/Index.vue has it bare in a table column definition?
    # No, it's in a format function probably.
    # Actually let's just do a generic replacement for the Intl.NumberFormat call itself if it's still there.
    # If we have _fmt or fmt defined.
    
    if modified:
        with open(filepath, 'w') as f:
            f.write(content)
        print(f"Refactored: {filepath}")
    else:
        print(f"Skipped: {filepath}")

for f in files:
    if os.path.exists(f):
        refactor_file(f)
    else:
        print(f"Not found: {f}")
