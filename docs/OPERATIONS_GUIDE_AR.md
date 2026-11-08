# دليل تشغيل البصمة وGoogle Drive وتثبيت التطبيق

هذا الدليل خاص بالنظام الموجود في المشروع. لم يتم تشغيل الربط على جهاز البصمة أو حساب Google الفعلي من خلال إعداد الكود وحده. رابط الموظفين: https://the-tables.elmotechsoft.online. قبل التشغيل، انشر نسخة الواجهة والخادم الجديدة واعمل نسخة احتياطية لقاعدة البيانات، ثم اختبر على بيئة تجريبية إن أمكن.

## أولاً: تجهيز جهاز البصمة

المشروع يحتوي على تكامل Hikvision ISAPI، والموديل المذكور في ملفات المشروع هو DS-K1T8003MF. تأكد من الموديل ونسخة الـ firmware وإمكانية استعلام سجلات ISAPI من الجهاز الفعلي. الكود والاختبارات المحاكية وحدها لا تؤكد التوافق مع جهازك.

1. ثبّت الجهاز ووصل الكهرباء والشبكة الداخلية طبقاً لتعليمات الفني. احجز له عنوان IP ثابتاً أو DHCP reservation على شبكة المكتب. لا تفتح منفذ الجهاز على الإنترنت.
2. اضبط التاريخ والوقت والمنطقة الزمنية على الجهاز، مع مراعاة التوقيت الصيفي، وتأكد أنها مطابقة لوقت المكتب وجهاز الـ bridge.
3. جهّز جهاز كمبيوتر أو mini-PC داخل المكتب، يعمل باستمرار ومتصل بنفس شبكة البصمة وبالإنترنت. ثبّت عليه Python 3 ونسخة المشروع المحتوية على `ops/device_bridge.py`. السكربت يستخدم مكتبات Python القياسية.
4. جهّز حساباً على الجهاز يستطيع قراءة سجلات ISAPI، وجدولاً يطابق **ERP user ID** لكل موظف مع **employee number كما هو على الجهاز**. سجّل البصمات على الجهاز بالطريقة المعتادة؛ الـ ERP لا يسحب قوالب البصمات أو ينشئها.
5. اتفق مع HR على كيفية تسجيل الحضور والانصراف قبل التفعيل: التنفيذ الحالي يعتبر **أول بصمة صحيحة في اليوم دخولاً والثانية خروجاً**، والبصمات اللاحقة لا تغير الحضور. لا يفترض أن الثانية هي آخر انصراف، لذلك لو هناك خروج وعودة أثناء اليوم لازم التحقق من ملاءمة القاعدة قبل الاعتماد عليها. لو يوجد حضور من الويب، النظام يحتفظ بوقت الدخول الأسبق، ولا يستبدل انصرافاً مسجلاً بالفعل.

## ثانياً: تسجيل الجهاز والربط بدون تغيير مستخدميه

1. افتح **Settings → Access Devices** بصلاحية `manage_devices`. إذا كان الجهاز مسجلاً، استخدم السجل الموجود ولا تنشئ نسخة مكررة. لو جديد اختر **Add device**، أدخل الاسم وعنوان IP والمنفذ والموديل، واترك **Automatic polling** مغلقاً عند استخدام bridge. الجهاز نفسه يظل Active.
2. افتح تفاصيل الجهاز. استخدم **Link an existing terminal employee** وأدخل ERP user ID ورقم الموظف الموجود على الجهاز، ثم **Link existing**. هذه الخطوة تسجل المطابقة في ERP فقط ولا ترسل مستخدمين أو بصمات أو صلاحيات للجهاز. تحقق من كل رقم قبل الحفظ؛ الرقم المحفوظ نص، وبالتالي الأصفار في بدايته مهمة. إذا ظهر تعارض راجع الربط الموجود قبل تغييره.
3. لا تستخدم **Send to device** أو **Send all employees** للحفاظ على بيانات الجهاز الحالية؛ هذه الأزرار تكتب على الجهاز. ولا تستخدم **Poll now** أو **Enable push** خلال تجربة الـ bridge. زر Test connection من الخادم لن ينجح مع IP داخلي إذا لم يوجد VPN، حتى لو الـ bridge داخل المكتب قادر على الوصول.

## ثالثاً: تشغيل الـ bridge في وضع التجربة

1. على خادم الـ backend، تأكد من نشر migrations والكود، وأن `APP_URL` يشير إلى عنوان الـ backend الصحيح القابل للوصول من المكتب عبر HTTPS، ثم نفّذ من مجلد الخادم:

```bash
php artisan devices:bridge DEVICE_ID
```

استبدل `DEVICE_ID` برقم الجهاز المسجل. الأمر يعطّل polling من الخادم لهذا الجهاز، ويضبط وضع **shadow**، ويطبع `ERP_BRIDGE_URL` والـ token لأول مرة فقط. احتفظ بالـ token في مكان خاص؛ لا تضعه في git أو ترسله في محادثة. لو لم تعد تملكه، يمكن للمسؤول استخدام `--rotate-token` وتحديث نسخة المكتب، مع العلم أنه يلغي التوكن السابق.

2. على كمبيوتر المكتب، ضع القيم التالية في بيئة تشغيل خاصة بحساب الخدمة. المثال توضيحي: استبدل القيم بين الأقواس، ولا تستخدم بيانات المثال كبيانات حقيقية.

```text
DEVICE_URL=http://<DEVICE_LAN_IP>:<PORT>
DEVICE_USERNAME=<READ_ONLY_DEVICE_ACCOUNT>
DEVICE_PASSWORD=<DEVICE_PASSWORD>
ERP_BRIDGE_URL=<EXACT_URL_PRINTED_BY_ARTISAN>
ERP_BRIDGE_TOKEN=<TOKEN_PRINTED_ONCE>
BRIDGE_START_AT=<GO_LIVE_DATE_AND_TIME_WITH_UTC_OFFSET>
BRIDGE_SPOOL=<PRIVATE_PERSISTENT_PATH_TO_SQLITE_FILE>
BRIDGE_INTERVAL_SECONDS=60
```

اختر `BRIDGE_START_AT` بتاريخ ووقت بداية التجربة مع offset الصحيح لمصر في ذلك التاريخ، مثلاً الصيغة `2026-11-08T09:00:00+02:00` كصيغة توضيحية فقط. لا تختار تاريخاً قديماً دون قصد؛ بعد التفعيل الأحداث المحفوظة بعد الحد تُطبَّق على الحضور. يجب أن يكون مسار قاعدة SQLite داخل مجلد موجود، خاص بحساب التشغيل، ويستمر بعد إعادة التشغيل. الجهاز يجيب عبر LAN؛ الـ bridge يتصل بالـ backend عبر HTTPS صادر فقط.

3. شغّل السكربت من نسخة المشروع على جهاز المكتب:

```bash
python3 ops/device_bridge.py
# على Windows حسب تثبيت Python:
python ops/device_bridge.py
```

اتركه يعمل، وجرّب بصمات دخول وخروج لعدد محدود من الموظفين. ظهور `Shadow mode: batch validated, attendance unchanged, local queue retained` يعني أن الخادم تحقّق من تنسيق الدفعة ولم يضف حضوراً؛ الأحداث محفوظة في SQLite على جهاز المكتب، وليست ظاهرة في سجل ERP بعد. لا تطبق اختبارات غير مقصود احتسابها بعد وقت البداية، لأن قائمة الانتظار ستُستورد عند التفعيل.

4. افحص الأحداث المنتظرة على جهاز المكتب (بدون طباعة بيانات اعتماد). استبدل المسار بمسار `BRIDGE_SPOOL`:

```bash
python3 -c 'import sqlite3,json; d=sqlite3.connect("PATH_TO_PRIVATE_SPOOL"); [(print(json.loads(p).get("employeeNoString") or json.loads(p).get("employeeNo"), t)) for t,p in d.execute("SELECT occurred,payload FROM events WHERE sent=0 ORDER BY occurred LIMIT 20")]'
```

قارن رقم الموظف والوقت مع الجهاز والنظام القديم، وتأكد من مطابقة الأرقام ومن أن نوع أحداث السماح/الرفض يفسَّر بشكل صحيح مع الـ firmware الفعلي. تحقق أيضاً من توقف الحضور عن الاستيراد في وضع shadow ومن عدم تكرار الأحداث عند انقطاع الإنترنت وعودته.

## رابعاً: التفعيل والمتابعة

بعد مراجعة العينة والاتفاق على قاعدة أول وثاني بصمة، نفّذ على خادم الـ backend:

```bash
php artisan devices:bridge DEVICE_ID --activate
```

يتحول الجهاز إلى وضع تطبيق الحضور، ويعيد الـ bridge إرسال الأحداث المنتظرة ثم الأحداث الجديدة بترتيب الوقت. افتح **Settings → Access Devices → Events** و**Attendance Management**، وطابق أوقات دخول وخروج عينة مع الجهاز. راجع الأحداث غير المرتبطة ورفض البصمة وعدم التكرار. عند الحاجة لإيقاف التطبيق والعودة إلى shadow نفّذ `php artisan devices:bridge DEVICE_ID`، وتابع الطابور قبل التفعيل مرة أخرى.

### التشغيل الدائم على Linux

المثال التالي لمسؤول Linux مع systemd. استبدل المسارات والبيانات بحسب الجهاز، ونفّذه من مجلد `erp-backend` المنشور أو المنسوخ لجهاز المكتب:

```bash
sudo useradd --system --no-create-home --shell /usr/sbin/nologin tables-bridge
sudo install -d -m 700 /etc/tables-bridge /var/lib/tables-bridge
sudo install -d -m 755 /opt/tables-bridge
sudo chown tables-bridge:tables-bridge /var/lib/tables-bridge
sudo chmod 700 /var/lib/tables-bridge
sudo install -m 644 ops/device_bridge.py /opt/tables-bridge/device_bridge.py
sudo editor /etc/tables-bridge/bridge.env
```

ضع متغيرات القسم الثالث في ملف `bridge.env` بصيغة `KEY=value`، وحدد `BRIDGE_SPOOL=/var/lib/tables-bridge/events.sqlite3`. احفظ الملف بصلاحيات `root` فقط: `sudo chmod 600 /etc/tables-bridge/bridge.env`. لا تضعه في مستودع المشروع. ثم أنشئ `/etc/systemd/system/tables-bridge.service` بالمحتوى التالي:

```ini
[Unit]
Description=The Tables fingerprint bridge
Wants=network-online.target
After=network-online.target

[Service]
Type=simple
User=tables-bridge
EnvironmentFile=/etc/tables-bridge/bridge.env
ExecStart=/usr/bin/python3 /opt/tables-bridge/device_bridge.py
Restart=always
RestartSec=15

[Install]
WantedBy=multi-user.target
```

ثم نفّذ `sudo systemctl daemon-reload` و`sudo systemctl enable --now tables-bridge`. للتحقق استخدم `sudo systemctl status tables-bridge` و`sudo journalctl -u tables-bridge -f`. احمِ ملف SQLite ونسخه الاحتياطية لأنه يحتوي على بيانات أحداث حضور؛ احتفظ به عند تحديث السكربت.

### التشغيل الدائم على Windows

استخدم حساب Windows مخصصاً للمهمة، وأنشئ مجلداً خاصاً مثل `C:\TablesBridge` وامنح الحساب ومسؤول الجهاز فقط صلاحية الوصول. انسخ `ops/device_bridge.py` إليه وثبّت Python لهذا الحساب. شغّل PowerShell **بنفس حساب الخدمة** مرة واحدة لحفظ بيانات الجهاز والتوكن بتشفير Windows المرتبط بهذا الحساب وعلى نفس الجهاز:

```powershell
Get-Credential | Export-Clixml C:\TablesBridge\device-credential.xml
Read-Host "Bridge token" -AsSecureString | ConvertFrom-SecureString | Set-Content C:\TablesBridge\bridge-token.txt
```

أدخل بيانات الجهاز في نافذة Get-Credential والتوكن المطبوع من الخادم في السؤال التالي. أنشئ ملف `C:\TablesBridge\start.ps1` في نفس المجلد، واستبدل القيم والمسار الفعلي لـ Python:

```powershell
$device = Import-Clixml C:\TablesBridge\device-credential.xml
$token = Get-Content C:\TablesBridge\bridge-token.txt | ConvertTo-SecureString
$env:DEVICE_URL = "http://<DEVICE_LAN_IP>:<PORT>"
$env:DEVICE_USERNAME = $device.UserName
$env:DEVICE_PASSWORD = $device.GetNetworkCredential().Password
$env:ERP_BRIDGE_URL = "<EXACT_URL_PRINTED_BY_ARTISAN>"
$env:ERP_BRIDGE_TOKEN = [System.Net.NetworkCredential]::new("", $token).Password
$env:BRIDGE_START_AT = "<GO_LIVE_DATE_AND_TIME_WITH_UTC_OFFSET>"
$env:BRIDGE_SPOOL = "C:\TablesBridge\events.sqlite3"
$env:BRIDGE_INTERVAL_SECONDS = "60"
& "C:\PATH_TO_PYTHON\python.exe" "C:\TablesBridge\device_bridge.py"
```

جرّب الملف يدوياً بنفس الحساب أولاً. ثم من Task Scheduler أنشئ مهمة تبدأ **At startup**، واختر نفس حساب الخدمة و**Run whether user is logged on or not**. الأمر هو المسار الكامل لـ `powershell.exe`، ووسيطاته `-NoProfile -File C:\TablesBridge\start.ps1`. فعّل إعادة المحاولة عند الفشل وتحقق من Last Run Result ومن أن الجهاز لا يدخل وضع النوم. يلزم أن تسمح سياسة تشغيل PowerShell الخاصة بالمؤسسة بالملف. بعد إعادة التشغيل تأكد أن المهمة تعمل وأن آخر اتصال يتحدث في ERP.

عند انقطاع الإنترنت يحتفظ الـ bridge بالأحداث ويعيد المحاولة. راقب عمر الاتصال وحجم قاعدة SQLite وفشل المهمة على جهاز المكتب؛ الكمبيوتر المطفي لا يرسل أحداثاً حتى يعود للعمل.


## خامساً: ربط Google Drive

1. اختر حساب Google تملكه الشركة وله صلاحية على المجلدات المطلوبة. الربط في النظام يستخدم اتصال Google واحداً مشتركاً، وليس حساباً مختلفاً لكل موظف. التكامل الحالي يطلب scope `https://www.googleapis.com/auth/drive` واسع الصلاحية على Drive للحساب الذي يوافق؛ راجع مالك الحساب وسياسات الشركة قبل الموافقة.
2. في Google Cloud أنشئ أو اختر Project، وفعل **Google Drive API**. اضبط Google Auth Platform: شاشة الموافقة، نوع الجمهور المناسب، الحسابات التجريبية عند الحاجة، والـ scope المطلوب. أنشئ OAuth Client من نوع **Web application**.
3. أضف **Authorized redirect URI** مطابقاً تماماً لعنوان الواجهة الفعلي:

```text
https://the-tables.elmotechsoft.online/auth/google/callback
```

4. على خادم الـ backend فقط، اضبط `GOOGLE_DRIVE_CLIENT_ID` و`GOOGLE_DRIVE_CLIENT_SECRET` و`GOOGLE_DRIVE_REDIRECT_URI` بنفس العنوان أعلاه. لا تضع الـ client secret في الواجهة أو المستودع. احتفظ بـ `APP_KEY` ثابتاً وبنسخة احتياطية آمنة لأنه يُستخدم لتشفير التوكنات. بعد تعديل إعدادات الإنتاج حدّث config cache وأعد تشغيل العمليات وفق طريقة النشر المتبعة.
5. افتح ERP بحساب لديه `manage_google_drive`، ثم **Settings → Google Drive → Connect Google Drive**، وسجّل دخول حساب الشركة ووافق على الصلاحيات بعد مراجعتها. قد يتطلب Google إعداد test users أو verification حسب نوع التطبيق والنطاقات المستخدمة. عد للنظام وتأكد من حالة الحساب.
6. افتح بروجكت تجريبياً → **Files → Connect Drive Folder**، اختر مجلداً للتجربة، ثم ارفع ملفاً تجريبياً وافتحه من Drive ومن ERP. جرّب مستخدماً مصرحاً وآخر غير مصرح، وراجع صلاحيات الوصول على Google نفسه؛ صلاحية فتح رابط Google تتبع أيضاً مشاركة المجلد في Google. لا تستخدم مجلد إنتاج قبل مراجعة الملفات والصلاحيات.

الكود يجدد access token تلقائياً عند استخدام ملفات Drive إذا كان refresh token متاحاً. فحص حالة الاتصال في الإعدادات لا يجدده بنفسه وقد يعرض انتهاء التوكن قبل عملية الملفات التالية؛ إذا فشلت عملية ملفات فعلية بسبب إلغاء التفويض أو عدم وجود refresh token، أعد الربط من حساب الشركة وراجع إعدادات Google.

## سادساً: تثبيت التطبيق على الهاتف

التطبيق PWA من رابط الموقع نفسه، وليس له رابط App Store أو Google Play في هذه النسخة. يحتاج اتصال إنترنت؛ لا يتم تقديم وضع عمل offline للبيانات.

* **iPhone:** افتح https://the-tables.elmotechsoft.online في Safari، ثم Share → Add to Home Screen → فعّل Open as Web App إن ظهر → Add.
* **Android:** افتح نفس الرابط في Chrome، ثم القائمة ⋮ → Install app أو Add to Home screen. قد يظهر زر Install app في صفحة Profile إذا قدمه المتصفح.

بعد نشر النسخة الجديدة، جرّب على جهاز فعلي تسجيل الدخول وفتح التاسكات، ثم فعّل الإشعارات من إعدادات التطبيق وسماح المتصفح/النظام إن لزم. إشعارات الخلفية تحتاج Web Push مهيأ على الخادم، ولا يمكن ضمان تسليمها أثناء إغلاق كل المتصفحات على كل الأنظمة.
