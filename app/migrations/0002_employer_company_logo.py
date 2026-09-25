from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('app', '0001_initial'),
    ]

    operations = [
        migrations.AddField(
            model_name='employer',
            name='company_logo',
            field=models.CharField(blank=True, max_length=500, null=True),
        ),
    ]