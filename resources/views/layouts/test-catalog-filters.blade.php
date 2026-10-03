<style>
.catalog-fields{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:end}
.catalog-fields label{display:block;font-size:.85rem;font-weight:700;margin-bottom:6px;color:#334155}
.catalog-fields select,.catalog-fields input{width:100%;min-height:44px;margin:0;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font:inherit}
.catalog-fields select:disabled{background:#f1f5f9;color:#64748b;cursor:not-allowed}
.catalog-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.catalog-help{font-size:.9rem;color:#475569;margin:12px 0 0}
@media(max-width:800px){.catalog-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:500px){.catalog-fields{grid-template-columns:1fr}}
</style>
<p>श्रेणी → परीक्षा → विषय → अध्याय चुनें। हर चयन पर अगली सूची अपने-आप बदलेगी।</p>
<form class="catalog-fields" data-test-catalog method="GET" action="{{ $catalogRoute }}">
    <div>
        <label for="catalog-category">1. श्रेणी / Category</label>
        <select id="catalog-category" name="category" data-clear="exam subject topic type q">
            <option value="">सभी श्रेणियाँ</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string)($filters['category'] ?? '') === (string)$category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="catalog-exam">2. परीक्षा / Exam</label>
        <select id="catalog-exam" name="exam" data-clear="subject topic type q" @disabled(empty($filters['category']) || $exams->isEmpty())>
            <option value="">{{ empty($filters['category']) ? 'पहले श्रेणी चुनें' : 'इस श्रेणी की सभी परीक्षाएँ' }}</option>
            @foreach($exams as $exam)
                <option value="{{ $exam->id }}" @selected((string)($filters['exam'] ?? '') === (string)$exam->id)>{{ $exam->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="catalog-subject">3. विषय / Subject</label>
        <select id="catalog-subject" name="subject" data-clear="topic type q" @disabled(empty($filters['exam']) || $subjects->isEmpty())>
            <option value="">{{ empty($filters['exam']) ? 'पहले परीक्षा चुनें' : ($subjects->isEmpty() ? 'विषय अनुसार टेस्ट उपलब्ध नहीं' : 'इस परीक्षा के सभी विषय') }}</option>
            @foreach($subjects as $subject)
                <option value="{{ $subject->id }}" @selected((string)($filters['subject'] ?? '') === (string)$subject->id)>{{ $subject->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="catalog-topic">4. अध्याय / Topic</label>
        <select id="catalog-topic" name="topic" data-clear="type q" @disabled(empty($filters['subject']) || $topics->isEmpty())>
            <option value="">{{ empty($filters['subject']) ? 'पहले विषय चुनें' : ($topics->isEmpty() ? 'अध्याय अनुसार टेस्ट उपलब्ध नहीं' : 'इस विषय के सभी अध्याय') }}</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected((string)($filters['topic'] ?? '') === (string)$topic->id)>{{ $topic->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="catalog-type">टेस्ट का प्रकार / Type</label>
        <select id="catalog-type" name="type">
            <option value="">सभी उपलब्ध प्रकार</option>
            @foreach($testTypes as $type)
                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="catalog-q">टेस्ट खोजें / Search</label>
        <input id="catalog-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="टेस्ट या परीक्षा का नाम">
    </div>
    <div class="catalog-actions"><button type="submit">खोजें / Apply</button><a href="{{ $catalogRoute }}">सभी फ़िल्टर हटाएँ</a></div>
</form>
<p class="catalog-help">अध्याय चुनने पर केवल उसी अध्याय के प्रश्नों वाले पूरे टेस्ट दिखेंगे। सभी अध्याय चुनने पर मिश्रित और विषय से संबंधित सेट भी दिखेंगे।</p>
<noscript><p class="catalog-help">हर चयन के बाद “खोजें / Apply” दबाएँ, फिर अगला विकल्प चुनें।</p></noscript>
<script>
(() => {
    const form = document.querySelector('[data-test-catalog]');
    if (!form) return;
    form.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => {
            (select.dataset.clear || '').split(' ').filter(Boolean).forEach(name => {
                const field = form.elements.namedItem(name);
                if (field) field.value = '';
            });
            form.requestSubmit();
        });
    });
})();
</script>
