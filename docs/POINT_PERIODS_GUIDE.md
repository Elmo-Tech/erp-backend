# 📊 Point Periods System - دليل الاستخدام الكامل

## 🎯 الهدف من النظام

نظام **Point Periods** يسمح بـ:

- ✅ تصفير النقاط كل شهرين (للبونص)
- ✅ الاحتفاظ بالهيستوري الكامل لكل الفترات
- ✅ مراجعة أداء الموظفين في أي فترة سابقة
- ✅ معرفة مين أخذ بونص في أي فترة

---

## 📁 البنية التقنية

### 1. الجداول الجديدة

#### `point_periods` - جدول الفترات

```sql
- id
- name (مثل: "Jan-Feb 2026")
- start_date
- end_date
- is_active (فترة واحدة فقط تكون active)
- closed_at
- closed_by
- notes
```

#### تعديل على `point_transactions`

```sql
+ period_id (Foreign Key to point_periods)
```

### 2. الموديلات

- `App\Models\PointPeriod` - إدارة الفترات
- `App\Models\PointTransaction` - تم تعديله ليدعم `period_id`

### 3. الخدمات

تم تحديث `App\Services\Points\PointService` بـ:

- `getUserPointsForPeriod()` - نقاط موظف في فترة معينة
- `getCurrentPeriodLeaderboard()` - الترتيب في الفترة الحالية
- `getUserPeriodHistory()` - هيستوري كل الفترات للموظف

---

## 🚀 الأوامر المتاحة

### 1. إغلاق الفترة الحالية وتصفير النقاط

```bash
# إغلاق عادي (يعمل فقط لو الفترة خلصت)
php artisan points:close-period

# إغلاق إجباري (في أي وقت)
php artisan points:close-period --force

# تجربة بدون تنفيذ (عرض البيانات فقط)
php artisan points:close-period --dry-run

# إغلاق مع ملاحظات
php artisan points:close-period --force --notes="End of Q1 bonus period"
```

### 2. ماذا يحدث عند إغلاق الفترة؟

1. ✅ **يغلق الفترة الحالية** ويحفظ:
    - تاريخ الإغلاق
    - من قام بالإغلاق
    - الملاحظات (إن وجدت)

2. ✅ **يصفر النقاط** لكل الموظفين:
    - `users.total_points` → 0

3. ✅ **ينشئ فترة جديدة** تلقائياً:
    - تبدأ من اليوم التالي لنهاية الفترة السابقة
    - مدتها شهرين

4. ✅ **الهيستوري محفوظ**:
    - كل الـ `point_transactions` موجودة
    - كل transaction مربوط بـ `period_id`

---

## 📊 أمثلة الاستخدام

### مثال 1: إغلاق الفترة في نهاية الشهرين

```bash
# في 28 فبراير 2026 (نهاية فترة يناير-فبراير)
php artisan points:close-period

# النتيجة:
# ✅ Period closed: Jan-Feb 2026
# ✅ Reset points for 15 employees
# ✅ New period created: Mar-Apr 2026
#    Start: 2026-03-01
#    End: 2026-04-30
```

### مثال 2: إغلاق مبكر (في منتصف الفترة)

```bash
# في 15 فبراير 2026 (قبل نهاية الفترة)
php artisan points:close-period --force --notes="Early closure for special bonus"

# النتيجة:
# ⚠️  Period end date not reached yet (13 days remaining).
# ✅ Forced closure approved
# ✅ Period closed with notes
```

### مثال 3: معاينة قبل التنفيذ

```bash
php artisan points:close-period --dry-run

# النتيجة:
# 📊 PERIOD STATISTICS:
#    Total Transactions: 245
#    Total Points Awarded: 1850
#    Total Points Deducted: 320
#    Net Points: 1530
#    Employees with Points: 12
#
# 🏆 TOP 10 LEADERBOARD:
# ┌──────┬─────────────┬──────────────────┬────────┐
# │ Rank │ Name        │ Email            │ Points │
# ├──────┼─────────────┼──────────────────┼────────┤
# │ 1    │ Ahmed Ali   │ ahmed@email.com  │ 250    │
# │ 2    │ Sara Hassan │ sara@email.com   │ 180    │
# └──────┴─────────────┴──────────────────┴────────┘
#
# ✅ Dry run completed. No changes made.
```

---

## 🔄 الجدولة التلقائية (Scheduler)

يمكنك جدولة إغلاق الفترة تلقائياً في `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// إغلاق تلقائي كل شهرين (في أول يوم من الشهر الفردي)
Schedule::command('points:close-period')
    ->monthlyOn(1, '00:01')
    ->when(fn() => now()->month % 2 === 1) // Jan, Mar, May, Jul, Sep, Nov
    ->onOneServer();
```

أو يدوياً كل شهرين:

```php
// في أول مارس
Schedule::command('points:close-period')->cron('1 0 1 3,5,7,9,11 *');
```

---

## 📈 استعلامات مفيدة

### 1. الحصول على الفترة النشطة

```php
use App\Models\PointPeriod;

$activePeriod = PointPeriod::getActivePeriod();
echo $activePeriod->name; // "Jan-Feb 2026"
```

### 2. نقاط موظف في الفترة الحالية

```php
use App\Services\Points\PointService;

$pointService = app(PointService::class);
$currentPoints = $user->total_points; // النقاط الحالية
```

### 3. نقاط موظف في فترة سابقة

```php
$period = PointPeriod::find(5); // فترة قديمة
$points = $period->getUserPoints($user->id);
```

### 4. هيستوري كل الفترات لموظف

```php
$history = $pointService->getUserPeriodHistory($user);

// النتيجة:
// [
//   {
//     "periodId": 3,
//     "periodName": "Nov-Dec 2025",
//     "startDate": "2025-11-01",
//     "endDate": "2025-12-31",
//     "closedAt": "2026-01-01 00:01:00",
//     "totalPoints": 180,
//     "transactionCount": 45,
//     "pointsEarned": 200,
//     "pointsDeducted": 20
//   },
//   ...
// ]
```

### 5. الترتيب (Leaderboard) للفترة الحالية

```php
$leaderboard = $pointService->getCurrentPeriodLeaderboard(10);

// النتيجة:
// [
//   {
//     "userId": 5,
//     "name": "Ahmed Ali",
//     "email": "ahmed@email.com",
//     "points": 250
//   },
//   ...
// ]
```

### 6. كل الفترات المغلقة

```php
$closedPeriods = PointPeriod::closed()
    ->orderBy('end_date', 'desc')
    ->get();

foreach ($closedPeriods as $period) {
    echo "{$period->name}: {$period->closed_at}\n";
}
```

---

## 🎁 سيناريو البونص

### الخطوات:

1. **خلال الشهرين**: الموظفين يجمعون نقاط
2. **نهاية الفترة**: تشغيل `php artisan points:close-period`
3. **حساب البونص**: استخدام الهيستوري لحساب البونص
4. **تصفير النقاط**: النقاط الحالية = 0
5. **فترة جديدة**: تبدأ تلقائياً

### مثال كود لحساب البونص:

```php
use App\Models\PointPeriod;

// الحصول على آخر فترة مغلقة
$lastPeriod = PointPeriod::closed()
    ->orderBy('closed_at', 'desc')
    ->first();

// الحصول على الترتيب
$leaderboard = $lastPeriod->getLeaderboard(100);

// حساب البونص حسب النقاط
foreach ($leaderboard as $rank => $employee) {
    $bonus = match(true) {
        $employee['points'] >= 200 => 1000, // بونص كبير
        $employee['points'] >= 100 => 500,  // بونص متوسط
        $employee['points'] >= 50 => 250,   // بونص صغير
        default => 0
    };

    // إضافة البونص للراتب أو حفظه في جدول منفصل
    // ...
}
```

---

## 🔐 الأمان والصلاحيات

يُنصح بإضافة صلاحيات للأوامر:

```php
// في PointPeriodPolicy.php
public function closePeriod(User $user): bool
{
    return $user->hasRole('super-admin') || $user->hasRole('hr-manager');
}
```

---

## 📝 الملاحظات المهمة

1. ✅ **الهيستوري محفوظ للأبد**: كل transaction مربوط بـ period_id
2. ✅ **فترة واحدة نشطة**: دائماً فيه فترة واحدة `is_active = true`
3. ✅ **التصفير آمن**: النقاط الحالية فقط تتصفر، الهيستوري يبقى
4. ⚠️ **لا يمكن التراجع**: بعد الإغلاق، لا يمكن إعادة فتح الفترة
5. 💡 **Dry Run أولاً**: استخدم `--dry-run` قبل التنفيذ الفعلي

---

## 🐛 استكشاف الأخطاء

### خطأ: "No active period found"

```bash
# الحل: إنشاء فترة يدوياً
php artisan tinker
>>> use App\Models\PointPeriod;
>>> PointPeriod::create([
...   'name' => 'Jan-Feb 2026',
...   'start_date' => '2026-01-01',
...   'end_date' => '2026-02-28',
...   'is_active' => true
... ]);
```

### خطأ: نقاط غير متطابقة

```bash
# الحل: إعادة حساب النقاط
php artisan tinker
>>> use App\Services\Points\PointService;
>>> $service = app(PointService::class);
>>> $user = User::find(5);
>>> $service->recalculateUserPoints($user);
```

---

## 📞 الدعم

للأسئلة أو المشاكل، راجع:

- الكود: `app/Console/Commands/ClosePointPeriodCommand.php`
- الموديل: `app/Models/PointPeriod.php`
- الخدمة: `app/Services/Points/PointService.php`

---

**تم إنشاء هذا النظام بواسطة Kiro AI** 🤖
