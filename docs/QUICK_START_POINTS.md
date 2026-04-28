# ⚡ Quick Start - Point Periods System

## 🚀 التثبيت السريع

### 1. تشغيل الـ Migration

```bash
cd erp-backend
php artisan migrate
```

هيتم إنشاء:

- ✅ جدول `point_periods`
- ✅ إضافة `period_id` لجدول `point_transactions`
- ✅ إنشاء أول فترة نشطة تلقائياً

---

## 📋 الأوامر الأساسية

### إغلاق الفترة وتصفير النقاط

```bash
# معاينة فقط (بدون تنفيذ)
php artisan points:close-period --dry-run

# إغلاق فعلي
php artisan points:close-period --force

# إغلاق مع ملاحظات
php artisan points:close-period --force --notes="نهاية فترة البونص الأولى"
```

---

## 🔄 الجدولة التلقائية

أضف في `routes/console.php`:

```php
// إغلاق تلقائي كل شهرين (في أول مارس، مايو، يوليو، إلخ)
Schedule::command('points:close-period')
    ->cron('1 0 1 3,5,7,9,11 *')
    ->onOneServer();
```

أو:

```php
// إغلاق في أول يوم من الأشهر الفردية
Schedule::command('points:close-period')
    ->monthlyOn(1, '00:01')
    ->when(fn() => now()->month % 2 === 1)
    ->onOneServer();
```

---

## 📊 الاستعلامات السريعة

### في الكود (PHP)

```php
use App\Models\PointPeriod;
use App\Services\Points\PointService;

$pointService = app(PointService::class);

// 1. الفترة الحالية
$activePeriod = PointPeriod::getActivePeriod();

// 2. نقاط الموظف الحالية
$currentPoints = $user->total_points;

// 3. نقاط موظف في فترة سابقة
$oldPeriod = PointPeriod::find(3);
$pointsInOldPeriod = $oldPeriod->getUserPoints($user->id);

// 4. هيستوري كل الفترات
$history = $pointService->getUserPeriodHistory($user);

// 5. الترتيب (Top 10)
$leaderboard = $pointService->getCurrentPeriodLeaderboard(10);
```

---

## ✅ التحقق من التثبيت

```bash
# تحقق من وجود الجداول
php artisan tinker
>>> \App\Models\PointPeriod::count()
# يجب أن يرجع 1 (الفترة الأولى)

>>> \App\Models\PointPeriod::getActivePeriod()
# يجب أن يرجع الفترة النشطة
```

---

## 🎯 الخطوات التالية

1. ✅ شغل الـ Migration
2. ✅ جرب `--dry-run` للمعاينة
3. ✅ أضف الجدولة التلقائية
4. ✅ اقرأ الدليل الكامل: `POINT_PERIODS_GUIDE.md`

---

**جاهز للاستخدام!** 🎉
