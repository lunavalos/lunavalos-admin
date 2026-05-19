import os
import re

file_path = "resources/js/Pages/Services/Addons/Index.vue"

with open(file_path, 'r') as f:
    content = f.read()

# Replace formatCurrency
old_func = """const formatCurrency = (value) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);"""

new_func = """const { fmt: _fmtMoney } = useMoney();
const formatCurrency = (amount, currency) => _fmtMoney(amount, currency);"""

if "new Intl.NumberFormat" in content:
    content = re.sub(r'const formatCurrency = \(value\)\s*=>\s*new Intl\.NumberFormat\(\'es-MX\', \{ style: \'currency\', currency: \'MXN\' \}\)\.format\(value\);', new_func, content)
    
    with open(file_path, 'w') as f:
        f.write(content)
    print(f"Fixed {file_path}")
else:
    print("Already clean")
