from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('app', '0011_perf_indexes'),
    ]

    operations = [
        migrations.AddIndex(
            model_name='notification',
            index=models.Index(
                fields=['user', 'is_read'],
                name='notif_user_read_idx',
            ),
        ),
    ]
