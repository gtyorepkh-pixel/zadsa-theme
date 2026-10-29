import re,base64,html as H
from extract import *
ROOT='/home/user/zadsa-theme'
OUT=ROOT+'/docs/mockups/service-redesign-tanks-riyadh.html'
spans=top_level(h); N=[h[a:b] for a,b in spans]
def rd(p): return open(p,encoding='utf-8').read()
# ---------- CSS ----------
css_theme=''.join(rd(f'{ROOT}/assets/css/{f}') for f in ('zad.css','zad-work.css','zad-ix.css'))
vars_css=re.search(r'<style id="zad-vars">(.*?)</style>',h,re.S).group(1)
fonts_css=''
for w in (400,500,700,800):
    b=base64.b64encode(open(f'{ROOT}/assets/fonts/tajawal-{w}.woff2','rb').read()).decode()
    fonts_css+='@font-face{font-family:"Tajawal";font-style:normal;font-weight:%d;font-display:swap;src:url(data:font/woff2;base64,%s) format("woff2")}'%(w,b)
# ---------- helpers ----------
def unlazy(s):
    s=re.sub(r'<noscript>.*?</noscript>','',s,flags=re.S)
    def fix(m):
        t=m.group(0)
        t=re.sub(r'\ssrc="data:image/svg\+xml;base64,[^"]*"','',t)
        t=t.replace(' data-src=',' src=').replace(' data-srcset=',' srcset=').replace(' data-sizes=',' sizes=').replace(' data-lazyloaded="1"','')
        return t
    return re.sub(r'<img\b[^>]*>',fix,s)
def txt(s): return re.sub(r'\s+',' ',H.unescape(re.sub(r'<[^>]+>',' ',s))).strip()
def wc(s): return len(txt(s).split())
def tag(kind,label):
    return f'<span class="mk__tag mk__tag--{kind}">{label}</span>'
def wrap(kind,label,inner,cls=''):
    return f'<div class="mk {cls}">{tag(kind,label)}{inner}</div>'
N=[unlazy(x) for x in N]
hero,stats,intro,wiz,mp,content,cov,types,equip,steps,price,signs,harms,works,faq,qnet,related,gallery=N
cov=cov.replace('<div class="cov__st"><b>30 يوماً</b><span>ضمان مكتوب</span></div>','',1)
# ---------- hero: add «from» line ----------
# (no «from» pill: the estimator card already shows «يبدأ من 150 ريال»)
# ---------- pricing: last-updated line ----------
price=price.replace('<p class="pkgx__note">الأسعار تقديرية وتتحدد بعد المعاينة</p>','<p class="pkgx__note">الأسعار تقديرية وتتحدد بعد المعاينة</p><p class="pkgx__upd">خزانات المنشآت (مجمعات ومطاعم ومدارس ومستشفيات): عقود دورية وسعر بعد المعاينة. آخر تحديث للأسعار: <time datetime="2026-10-29">29 أكتوبر 2026</time></p>',1)
# ---------- steps: merge the equipment into the steps ----------
tools={1:'مضخات شفط وتفريغ',2:'فرش طويلة وأدوات زوايا، مع معدات تهوية ودخول آمن للخزانات المغلقة',3:'مواد تعقيم مخصصة للخزانات، تُطبق حسب تعليماتها',4:'أجهزة ضغط ماء مضبوطة حسب نوع الجدار'}
def add_tool(m):
    i=int(m.group(2)); s=m.group(0)
    if i in tools: s=s.replace('</li>',f'<p class="hsteps__tool"><b>الأداة:</b> {tools[i]}</p></li>',1)
    return s
def _tool(m):
    i=int(m.group(1)); li=m.group(0)
    return li.replace('</li>','<p class="hsteps__tool"><b>الأداة:</b> '+tools[i]+'</p></li>',1) if i in tools else li
steps=re.sub(r'<li><span class="hsteps__n">(\d)</span>.*?</li>',_tool,steps,flags=re.S)
# ---------- needs section (merged) ----------
def demote(block,wrapper_cls):
    b=re.sub(r'^<section class="[^"]*"([^>]*)><div class="wrap">',r'<div class="need__grp \g<0>',block)
    return b
def inner_of(sec):  # inside of <section …><div class="wrap"> … </div></section>
    m=re.match(r'<section[^>]*><div class="wrap">(.*)</div></section>\s*$',sec,re.S); return m.group(1) if m else sec
def h2_to_h3(s): return s.replace('<h2','<h3',1).replace('</h2>','</h3>',1)
def head_to_group(inner):  # header.sec__head with h2 → h3 (keep eyebrow/p)
    return re.sub(r'<h2([^>]*)>(.*?)</h2>',r'<h3\1>\2</h3>',inner,count=1,flags=re.S)
w_in=head_to_group(inner_of(wiz)).replace('هل يحتاج خزانك تنظيفاً الآن؟','افحص خزانك في دقيقتين'); m_in=head_to_group(inner_of(mp))
s_in=re.search(r'<div class="cardgrid cardgrid--2">.*</div>\s*$',inner_of(signs),re.S).group(0)
hm_in=re.search(r'<div class="cardgrid">.*</div>\s*$',inner_of(harms),re.S).group(0)
needs=f'''<section class="sec" id="needs"><div class="wrap">
<header class="sec__head"><span class="eyebrow">علامات الحاجة للتنظيف</span><h2>هل يحتاج خزانك تنظيفاً الآن؟</h2>
<p>غالباً نعم إذا ظهرت أي علامة من العلامات التالية. افحص خزانك لتعرف الخطة المناسبة.</p></header>
<details class="need__details"><summary class="btn btn--accent">ابدأ الفحص: 6 أسئلة سريعة تعرف بعدها الخطة المناسبة لخزانك</summary><div class="need__grp ix ix--wiz" id="ix-wiz">{w_in}</div></details>
<div class="need__grp"><header class="sec__head"><h3>علامات تخبرك أنك تحتاج تنظيف خزانات بالرياض</h3></header>{s_in}</div>
<div class="need__grp ix ix--map" id="ix-map">{m_in}</div>
<div class="need__grp"><header class="sec__head"><h3>ماذا يحدث إذا أجّلت التنظيف؟</h3></header>{hm_in}</div>
</div></section>'''
# ---------- knowledge sections (regroup the 8 editor H2s) ----------
body=re.search(r'<div class="prose entry-content">(.*)</div></div></section>\s*$',content,re.S).group(1)
parts=re.split(r'(?=<h2 class="wp-block-heading">)',body)[1:]
P={i+1:p for i,p in enumerate(parts)}
def sub(p): return p.replace('<h2 class="wp-block-heading">','<h3 class="wp-block-heading">').replace('</h2>','</h3>')
def ksec(inner,idd=''):
    return f'<section class="sec"{(" id=%s"%chr(34)+idd+chr(34)) if idd else ""}><div class="wrap wrap--narrow"><div class="prose entry-content">{inner}</div></div></section>'
K1=ksec(P[1]+sub(P[5])+sub(P[6]))
K2=ksec(P[3]+sub(P[4]))
K3=ksec(P[2]+sub(P[7]))
K4=ksec(P[8])
K5=ksec('''<h2 class="wp-block-heading">كيف أختار شركة تنظيف خزانات؟</h2>
<p class="wp-block-paragraph">اسأل أي شركة عن هذه النقاط قبل أن تحجز، وستعرف بسرعة من يعمل بطريقة صحيحة:</p>
<ul class="wp-block-list"><li>هل يتم <strong>تفريغ الخزان بالكامل</strong> وفرك القاع والجدران قبل التعقيم؟</li><li>ما <strong>مادة التعقيم</strong> المستخدمة وكيف تُشطف بعدها؟</li><li>هل يُسلَّم <strong>تقرير مصور</strong> قبل وبعد؟</li><li>هل <strong>الضمان مكتوب</strong> بمدة واضحة، وماذا يغطي؟</li><li>هل <strong>السعر مكتوب قبل البدء</strong> وما الذي يغيّره؟</li></ul>''','choose')
K6=ksec('''<h2 class="wp-block-heading">كيف أحجز وكيف أدفع؟</h2>
<ol class="wp-block-list"><li>تواصل معنا عبر واتساب أو الاتصال، وأخبرنا بنوع الخزان وحيّك.</li><li>نحدد موعداً في نفس اليوم أو اليوم التالي.</li><li>ننفذ الزيارة ونسلّمك تقريراً مصوراً.</li></ol>
<p class="mk__todo">⟵ سطر طرق الدفع يُقرأ من الإعدادات (غير مكتوب في الموقع الآن، فلا يظهر حتى يُدخَل).</p>''','book')
# ---------- video (optional) ----------
video='''<section class="sec sec--dark" id="video"><div class="wrap wrap--narrow"><header class="sec__head"><span class="eyebrow">شاهد الفرق بنفسك</span><h2>شاهد كيف نغسل الخزان</h2></header>
<div class="vframe" style="--poster:linear-gradient(135deg,#0a3947,#0c687e)"><button type="button" class="vframe__play" aria-label="تشغيل الفيديو"><svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M8 5v14l11-7Z"/></svg></button></div></div></section>'''
# ---------- business data + map ----------
biz='''<section class="sec sec--tint" id="biz"><div class="wrap">
<header class="sec__head"><span class="eyebrow">من نحن وأين نخدم</span><h2>مناطق خدمة تنظيف الخزانات وبيانات الشركة</h2></header>
<div class="biz"><div class="biz__card"><ul class="biz__list">
<li><b>الشركة</b><span>شركة زاد السعودية للصيانة والنظافة</span></li>
<li><b>نطاق الخدمة</b><span>الرياض بجميع أحيائها</span></li>
<li><b>العنوان</b><span>2851 شارع عبدالملك بن مروان، حي العليا، الرياض 12611</span></li>
<li><b>الهاتف</b><span dir="ltr"><a href="tel:0552744437">0552744437</a></span></li>
<li><b>ساعات العمل</b><span>من 8 صباحاً إلى 11 مساءً طوال أيام الأسبوع</span></li>
<li><b>السجل التجاري</b><span>1010769699</span></li></ul></div>
<div class="biz__map"><div class="biz__pin">'''+'''<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>'''+'''</div><a class="btn btn--accent" href="https://www.google.com/maps?q=24.774265,46.738586" target="_blank" rel="noopener">عرض الموقع على خرائط جوجل</a><small>تُحمَّل الخريطة عند الضغط فقط (لا تؤثر على سرعة الصفحة)</small></div></div></div></section>'''
# ---------- works + gallery + expert + reviews ----------
arts=re.findall(r'<article class="scard scard--post zw-card zw-card--pro">.*?</article>',works,re.S)
first=arts[0]; w2=works.replace(arts[1],'') if len(arts)>1 else works
w2=w2.replace('data-n="2"','data-n="1"')
gal=re.search(r'<div class="gal">.*?</div>',gallery,re.S).group(0)
w2=re.sub(r'</div></section>\s*$','<div class="need__grp"><header class="sec__head"><h3>صور من زيارات تنظيف خزانات بالرياض</h3></header>'+gal+'</div></div></section>',w2.rstrip(),count=1,flags=re.S)
expert='''<section class="sec"><div class="wrap wrap--narrow"><div class="expert">
<span class="expert__av" aria-hidden="true">م</span><div><small>راجع المحتوى الفني</small><h3><a href="http://zadksa.local/author/mahmoud/">محمود القحطاني</a></h3><p>محرر المحتوى الفني في زاد السعودية. يعتمد في إعداد المحتوى على زيارات مواقع التنفيذ، وملاحظات فرق العمل، وأسئلة العملاء الفعلية.</p></div></div></div></section>'''
reviews_slot='<div class="mk__empty"><b>مراجعات عملاء حقيقية</b><br>تظهر هنا فقط عند إدخال مراجعات فعلية (الاسم + الحي + التاريخ + نص المراجعة) مع رابط «كل المراجعات على خرائط جوجل». لا يوجد شيء يُعرض للزائر الآن.</div>'
# ---------- FAQ merged ----------
qn=re.search(r'<div class="qnet">.*?</div>',qnet,re.S).group(0); qall=re.search(r'<p class="qnet__all">.*?</p>',qnet,re.S).group(0)
DROP=['كم سعر تنظيف الخزانات بالرياض','كم مرة يجب تنظيف خزان المياه','هل تنظفون خزانات العمائر والمنشآت','هل تصلحون شروخ الخزان وتسريبه']
def _drop(m):
    return '' if any(d in m.group(0) for d in DROP) else m.group(0)
faq=re.sub(r'<details class="faq__item">.*?</details>',_drop,faq,flags=re.S)
faq2=re.sub(r'</div></section>\s*$','<div class="need__grp"><header class="sec__head"><h3>أسئلة تفصيلية عن تنظيف الخزانات</h3></header>'+qn+qall+'</div></div></section>',faq.rstrip(),count=1,flags=re.S)
# ---------- related ----------
def card(u,t,d): return f'<article class="scard scard--lite"><a class="scard__lite" href="{u}"><h3 class="scard__title">{t}</h3></a><p class="scard__desc">{d}</p></article>'
rel='<section class="sec sec--mint"><div class="wrap"><header class="sec__head"><span class="eyebrow">خدمات أخرى</span><h2>خدمات أخرى قد تحتاجها</h2></header><div class="sgrid">'+''.join([
 card('http://zadksa.local/cleaning/tanks/jeddah/','تنظيف خزانات بجدة','نفس الخدمة في مدينة أخرى'),
 card('http://zadksa.local/cleaning/air-conditioning/riyadh/','تنظيف مكيفات بالرياض','غسيل المكيفات بعد الخزانات في نفس الزيارة'),
 card('http://zadksa.local/cleaning/sofa/riyadh/','تنظيف كنب بالرياض','للبيت نفسه في نفس الأسبوع')])+'</div></div></section>'
# ---------- compose ----------
def sec(kind,label,x,cls=''): return wrap(kind,label,x,cls)
order=[
 sec('keep','① الهيرو — كما هو (من غير سطر سعر إضافي: بطاقة التسعير الفوري فيه تعرض «يبدأ من»)',hero),
 sec('moved','② الأسعار — أول محتوى تحت الهيرو مباشرة (كانت 11 من 18) + سطر المنشآت + آخر تحديث',price),
 sec('merged','③ الخطوات — منقولة لفوق + «الأداة» لكل خطوة (بدل قسم المعدات)',steps),
 sec('new','④ فيديو — يظهر فقط عند وجود رابط (لا يوجد رابط في هذه الصفحة الآن)',video),
 sec('merged','⑤ علامات الحاجة — مدمج: هل يحتاج + علامات + من أين يبدأ التلوث + ماذا لو أجّلت (الاختبار مطوي بزر)',needs),
 sec('merged','⑥ الفرق بين التنظيف والتعقيم + متى الصيانة/العزل + لماذا تعود الطحالب',K1),
 sec('merged','⑦ كم مرة ننظف + الرائحة بعد التنظيف',K2),
 sec('merged','⑧ الأرضي أم العلوي + خزانات العمائر',K3),
 sec('merged','⑨ هل الماء صالح للشرب؟',K4),
 sec('new','⑩ كيف أختار شركة تنظيف خزانات؟ — مسودة محتوى للمراجعة',K5),
 sec('new','⑪ كيف أحجز وكيف أدفع؟ — مسودة محتوى للمراجعة',K6),
 sec('keep','⑫ أحياء الرياض (روابط نصية) — بعد حذف مربع «30 يوماً ضمان» المكرر منها',cov),
 sec('new','⑬ بيانات الشركة + الخريطة (من الإعدادات؛ بيانات حقيقية) — الخريطة تُحمَّل عند الضغط',biz),
 sec('merged','⑭ أعمالنا + صور الزيارات في قسم واحد (واستُبعد عمل «كنب وموكيت»)',w2),
 sec('new','⑭ بطاقة المشرف (من حساب الكاتب الحقيقي)',expert),
 sec('new','⑭ المراجعات الحقيقية — لا تظهر إلا ببيانات',f'<section class="sec"><div class="wrap">{reviews_slot}</div></section>','mk--ghost'),
 sec('merged','⑮ الأسئلة الشائعة — 4 أسئلة غير مكررة + 6 أسئلة تفصيلية = 10 في قسم واحد',faq2),
 sec('new','⑯ خدمات أخرى قد تحتاجها',rel),
]
removed='''<div class="mk-removed"><h3>اللي اتشال من الصفحة (مكرر)</h3><ul>
<li><b>المقدمة</b> («شركة تنظيف خزانات بالرياض من زاد…») — كررت الأسعار الثلاثة والضمان وخطوات الزيارة الست حرفياً تقريباً (والسعر نفسه في سؤال الأسئلة). رابط القسم الأب (تنظيف الخزانات) موجود في مسار التنقل.</li>
<li><b>شريط الأرقام</b> (150 / 250 / 30 يوم / مرتان) — الأربعة مكررة في الهيرو والأسعار والأسئلة.</li>
<li><b>«أنواع تنظيف الخزانات»</b> — خمس بطاقات هي نفس باقات الأسعار الخمس؛ المنفرد فيها (المنشآت) انتقل لسطر تحت الأسعار.</li>
<li><b>قسم المعدات</b> — عنوانه ثابت في كل الخدمات؛ معلوماته في سطر «الأداة» داخل الخطوات.</li>
<li><b>مربع «30 يوماً ضمان مكتوب»</b> داخل قسم الأحياء — الضمان مذكور في الهيرو والباقات وسؤال الضمان.</li>
<li><b>«صور من زيارات» و«أسئلة تفصيلية» كأقسام مستقلة</b> — اندمجا في «أعمالنا» وفي الأسئلة.</li>
<li><b>أسئلة مكررة:</b> «كم سعر» (قسم الأسعار)، «كم مرة» (قسم كم مرة ننظف)، «خزانات العمائر والمنشآت» (قسم الأرضي والعلوي وسطر المنشآت)، «تصلحون شروخ» (قسم الصيانة والعزل وباقة العزل).</li>
<li><b>عمل «كنب وموكيت»</b> من قسم أعمال الخزانات — ليس عن الخزانات.</li></ul>
<p>لا يتغير: الهيدر والفوتر والشريط السفلي. لا CTA أخير (قرارك في 4.0.9).</p></div>'''
summary='''<div class="mk-top" dir="rtl"><div class="mk-top__bar"><b>نموذج تصميم — لم يُطبَّق على الموقع</b><span>صفحة /cleaning/tanks/riyadh/ بمحتواها الحقيقي</span>
<button type="button" id="mk-toggle">إخفاء الملاحظات</button></div>
<div class="mk-top__legend"><i class="mk__tag mk__tag--keep">باقٍ كما هو</i><i class="mk__tag mk__tag--moved">منقول</i><i class="mk__tag mk__tag--merged">مدمج</i><i class="mk__tag mk__tag--new">جديد</i></div></div>'''
mk_css='''
.mk{position:relative}
.mk__tag{display:block;position:relative;z-index:30;font:700 13px/1.5 Tajawal,sans-serif;padding:6px 16px;color:#fff}
.mk-top__legend .mk__tag{display:inline-block;margin-inline-end:8px;border-radius:999px;padding:3px 12px}
.mk__tag--keep{background:#5b6b73}.mk__tag--moved{background:#0c687e}.mk__tag--merged{background:#7a3fb4}.mk__tag--new{background:#1b8a4b}
.mk-hide .mk__tag,.mk-hide .mk__todo,.mk-hide .mk-removed{display:none}
.mk-top{position:sticky;top:0;z-index:99999;background:#0f172a;color:#fff;font:600 14px Tajawal,sans-serif}
.mk-top__bar{display:flex;gap:14px;align-items:center;flex-wrap:wrap;padding:10px 16px}.mk-top__bar span{opacity:.8;font-weight:500}
.mk-top__bar button{margin-inline-start:auto;border:0;border-radius:999px;padding:6px 14px;font:700 13px Tajawal;background:#f49400;color:#1a1200;cursor:pointer}
.mk-top__legend{padding:0 16px 10px}
.mk__empty{border:2px dashed #9aa7ad;border-radius:12px;padding:18px;text-align:center;color:#51616a;background:rgba(255,255,255,.6)}
.mk__todo{border:2px dashed #9aa7ad;border-radius:10px;padding:8px 12px;color:#51616a;font-size:.9rem}
.mk--ghost .mk__tag{background:#1b8a4b}
.mk-removed{max-width:1000px;margin:30px auto;padding:20px 24px;border:2px dashed #b32d2e;border-radius:14px;background:#fff7f7;color:#222}
.mk-removed h3{margin:0 0 10px;color:#b32d2e}.mk-removed li{margin:6px 0;line-height:1.8}
.hdr{position:relative!important}
'''
new_blocks_css='''
/* === CSS المقترح لإضافته للثيم (للكتل الجديدة فقط، بنفس متغيرات الألوان) === */
.shero__from{display:inline-flex;flex-wrap:wrap;align-items:baseline;gap:6px 10px;margin:16px 0 0;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.14);color:#fff;font-weight:700}
.shero__from b{font-size:1.35rem;color:var(--accent)}
.pkgx__upd{margin:10px 0 0;text-align:center;color:var(--muted);font-size:.9rem}
.hsteps__tool{margin:10px 0 0;padding:8px 12px;border-radius:10px;background:rgba(12,104,126,.08);font-size:.9rem;line-height:1.7}
.need__details{margin:26px 0 0}.need__details>summary{cursor:pointer;list-style:none;display:flex;width:100%;max-width:640px;margin-inline:auto;justify-content:center}.need__details>summary::-webkit-details-marker{display:none}.need__details[open]>summary{margin-bottom:10px}
.need__grp{margin-top:34px}.need__grp>.sec__head h3,.need__grp>header h3{font-size:1.25rem;margin:0 0 12px}
.need__grp.ix{padding:0}.need__grp .wrap{padding-inline:0}
.biz{display:grid;gap:20px;grid-template-columns:1fr}
.biz__card,.biz__map{background:var(--surface,#fff);border:1px solid var(--line,#dfe6e9);border-radius:var(--r,14px);padding:22px}
.biz__list{list-style:none;margin:0;padding:0;display:grid;gap:14px}.biz__list li{display:grid;gap:2px}.biz__list b{color:var(--muted);font-size:.85rem}.biz__list span{font-weight:700}
.biz__map{display:grid;place-items:center;align-content:center;gap:12px;min-height:260px;text-align:center;background:repeating-linear-gradient(45deg,rgba(12,104,126,.05) 0 12px,rgba(12,104,126,.09) 12px 24px)}
.biz__pin{color:var(--primary)}.biz__map small{color:var(--muted)}
.expert{display:flex;gap:16px;align-items:center;background:var(--surface,#fff);border:1px solid var(--line,#dfe6e9);border-radius:var(--r,14px);padding:18px 20px}
.expert__av{flex:none;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;background:var(--primary);color:#fff;font-weight:800;font-size:1.4rem}
.expert small{color:var(--muted)}.expert h3{margin:2px 0 6px;font-size:1.1rem}.expert p{margin:0;color:var(--muted);line-height:1.8;font-size:.95rem}
@media(min-width:860px){.biz{grid-template-columns:1.1fr .9fr}}
.zw-grid4:has(> :only-child){grid-template-columns:minmax(0,420px);justify-content:center}
'''
SPRITE=re.search(r'<svg[^>]*width="0"[^>]*>.*?</svg>',h,re.S).group(0)
pre_all=h[h.find('<body'):h.find('<main')]
hdr=re.search(r'<a class="skip".*?</header>',pre_all,re.S).group(0)
suf=h[h.rfind('</main>')+7:]
ftr=suf[:suf.find('</footer>')+9]
dock_i=suf.find('<div class="dock"'); dock=suf[dock_i:suf.find('</div>',dock_i)+6]
hdr=unlazy(hdr); ftr=unlazy(ftr)
doc=f'''<!DOCTYPE html>
<html dir="rtl" lang="ar"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>نموذج تصميم صفحة الخدمة — تنظيف خزانات بالرياض</title>
<style>{fonts_css}</style><style id="zad-vars">{vars_css}</style><style id="zad-theme-css">{css_theme}</style>
<style id="mk-new-blocks">{new_blocks_css}</style><style id="mk-chrome">{mk_css}</style></head>
<body class="rtl single single-cleaning zad" id="mk-body">{SPRITE}{summary}{hdr}<main id="main">{''.join(order)}{removed}</main>{ftr}{dock}
<script>{rd(ROOT+'/assets/js/zad-ix.js')}</script><script>document.getElementById('mk-toggle').addEventListener('click',function(){{var b=document.getElementById('mk-body');b.classList.toggle('mk-hide');this.textContent=b.classList.contains('mk-hide')?'إظهار الملاحظات':'إخفاء الملاحظات';}});
</script></body></html>'''
open(OUT,'w',encoding='utf-8').write(doc)
print('written',OUT,len(doc)//1024,'KB')
# stats
print('H2 count in main:',len(re.findall(r'<h2',''.join(order))),' words:',wc(''.join(order)))
