import os
import re
from django.core.management.base import BaseCommand
from django.db import connection
from django.conf import settings

def parse_sql_inserts(sql_text):
    statements = []
    in_string = False
    escape = False
    current_stmt = []
    
    i = 0
    n = len(sql_text)
    
    while i < n:
        char = sql_text[i]
        
        if in_string:
            current_stmt.append(char)
            if escape:
                escape = False
            elif char == '\\':
                escape = True
            elif char == "'":
                in_string = False
        else:
            if char == "'":
                in_string = True
                current_stmt.append(char)
            elif char == ';':
                stmt_str = ''.join(current_stmt).strip()
                if stmt_str.upper().startswith('INSERT INTO'):
                    statements.append(stmt_str)
                current_stmt = []
            elif char == '-' and i + 1 < n and sql_text[i+1] == '-':
                while i < n and sql_text[i] != '\n':
                    i += 1
                continue
            elif char == '/' and i + 1 < n and sql_text[i+1] == '*':
                i += 2
                while i + 1 < n and not (sql_text[i] == '*' and sql_text[i+1] == '/'):
                    i += 1
                i += 1
            else:
                current_stmt.append(char)
        i += 1
        
    return statements

def convert_mysql_insert_to_sqlite(stmt):
    m = re.match(r'INSERT INTO `?(\w+)`?\s*\((.*?)\)\s*VALUES\s*(.*)', stmt, re.DOTALL | re.IGNORECASE)
    if not m:
        return None, None, None
        
    table_name = m.group(1)
    cols_raw = m.group(2)
    values_raw = m.group(3).strip()
    
    cols = [c.strip('` ').strip() for c in cols_raw.split(',')]
    cols_joined = ', '.join([f'"{c}"' for c in cols])
    
    cleaned_values = re.sub(r"(?<!\\)\\'", "''", values_raw)
    cleaned_values = cleaned_values.replace("\\'", "''")
    cleaned_values = cleaned_values.replace('\\"', '"')
    cleaned_values = cleaned_values.replace('\\r\\n', '\r\n').replace('\\n', '\n')
    
    return table_name, cols_joined, cleaned_values

SKILLS_DATA = [
    # Programming Languages
    ('php', 'Programming Languages'),
    ('javascript', 'Programming Languages'),
    ('python', 'Programming Languages'),
    ('java', 'Programming Languages'),
    ('c++', 'Programming Languages'),
    ('c#', 'Programming Languages'),
    ('sql', 'Programming Languages'),
    ('mysql', 'Programming Languages'),
    ('html', 'Programming Languages'),
    ('css', 'Programming Languages'),
    ('typescript', 'Programming Languages'),
    ('ruby', 'Programming Languages'),
    ('go', 'Programming Languages'),
    ('rust', 'Programming Languages'),
    ('swift', 'Programming Languages'),
    ('kotlin', 'Programming Languages'),
    ('scala', 'Programming Languages'),
    ('perl', 'Programming Languages'),
    # Frameworks & Libraries
    ('react', 'Frameworks & Libraries'),
    ('angular', 'Frameworks & Libraries'),
    ('vue', 'Frameworks & Libraries'),
    ('node', 'Frameworks & Libraries'),
    ('laravel', 'Frameworks & Libraries'),
    ('django', 'Frameworks & Libraries'),
    ('spring', 'Frameworks & Libraries'),
    ('express', 'Frameworks & Libraries'),
    ('jquery', 'Frameworks & Libraries'),
    ('bootstrap', 'Frameworks & Libraries'),
    ('tailwind', 'Frameworks & Libraries'),
    ('sass', 'Frameworks & Libraries'),
    ('less', 'Frameworks & Libraries'),
    ('webpack', 'Frameworks & Libraries'),
    ('npm', 'Frameworks & Libraries'),
    ('yarn', 'Frameworks & Libraries'),
    # Tools & Technologies
    ('git', 'Tools & Technologies'),
    ('github', 'Tools & Technologies'),
    ('docker', 'Tools & Technologies'),
    ('kubernetes', 'Tools & Technologies'),
    ('aws', 'Tools & Technologies'),
    ('azure', 'Tools & Technologies'),
    ('gcp', 'Tools & Technologies'),
    ('jenkins', 'Tools & Technologies'),
    ('ci/cd', 'Tools & Technologies'),
    ('agile', 'Tools & Technologies'),
    ('scrum', 'Tools & Technologies'),
    ('devops', 'Tools & Technologies'),
    ('linux', 'Tools & Technologies'),
    ('unix', 'Tools & Technologies'),
    ('windows', 'Tools & Technologies'),
    # Databases
    ('postgresql', 'Databases'),
    ('mongodb', 'Databases'),
    ('redis', 'Databases'),
    ('oracle', 'Databases'),
    ('sqlite', 'Databases'),
    ('mariadb', 'Databases'),
    # Accounting & Finance
    ('financial analysis', 'Accounting & Finance'),
    ('budgeting', 'Accounting & Finance'),
    ('bookkeeping', 'Accounting & Finance'),
    ('payroll', 'Accounting & Finance'),
    ('auditing', 'Accounting & Finance'),
    ('tax preparation', 'Accounting & Finance'),
    ('excel', 'Accounting & Finance'),
    ('quickbooks', 'Accounting & Finance'),
    ('sap', 'Accounting & Finance'),
    # Soft Skills & Management
    ('communication', 'Soft Skills'),
    ('teamwork', 'Soft Skills'),
    ('problem solving', 'Soft Skills'),
    ('leadership', 'Soft Skills'),
    ('time management', 'Soft Skills'),
    ('project management', 'Soft Skills'),
    ('critical thinking', 'Soft Skills'),
    ('adaptability', 'Soft Skills'),
]

class Command(BaseCommand):
    help = 'Seeds the database from u190037990_mb_db.sql and default skills'

    def handle(self, *args, **options):
        sql_file = settings.BASE_DIR / 'u190037990_mb_db.sql'
        if not sql_file.exists():
            self.stdout.write(self.style.ERROR(f"SQL file not found at: {sql_file}"))
            return

        with open(sql_file, 'r', encoding='utf-8', errors='ignore') as f:
            sql_content = f.read()

        statements = parse_sql_inserts(sql_content)
        self.stdout.write(f"Found {len(statements)} full INSERT statements.")

        with connection.cursor() as cursor:
            if connection.vendor == 'sqlite':
                cursor.execute('PRAGMA foreign_keys = OFF;')
            elif connection.vendor == 'mysql':
                cursor.execute('SET FOREIGN_KEY_CHECKS = 0;')

            for stmt in statements:
                table_name, cols_joined, cleaned_values = convert_mysql_insert_to_sqlite(stmt)
                if not table_name:
                    continue
                    
                sql_exec = f'INSERT OR REPLACE INTO "{table_name}" ({cols_joined}) VALUES {cleaned_values};'
                try:
                    cursor.execute(sql_exec)
                    self.stdout.write(self.style.SUCCESS(f"[OK] Seeded table: {table_name}"))
                except Exception as e:
                    self.stdout.write(self.style.WARNING(f"[WARN] Error inserting into {table_name}: {e}"))

            # Seed skills
            from app.models import Skill
            for skill_name, category in SKILLS_DATA:
                Skill.objects.get_or_create(
                    skill_name=skill_name,
                    defaults={'category': category, 'status': 'active'}
                )
            self.stdout.write(self.style.SUCCESS("[OK] Seeded skills dataset"))

            if connection.vendor == 'sqlite':
                cursor.execute('PRAGMA foreign_keys = ON;')
            elif connection.vendor == 'mysql':
                cursor.execute('SET FOREIGN_KEY_CHECKS = 1;')

        self.stdout.write(self.style.SUCCESS("Database seeding completed successfully!"))
