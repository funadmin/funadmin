{{-- 读者反馈：桌面端在右栏，窄屏在文末；两处由 reader.js 同步状态。 --}}
<section class="feedback {{ $placement === 'rail' ? 'rail-feedback' : 'article-feedback' }}" data-feedback data-slug="{{ $document['slug'] }}" aria-label="文档反馈">
  <h2 class="feedback-title">本文是否对您有帮助？</h2>
  <div class="feedback-votes">
    <button class="feedback-vote" type="button" data-feedback-vote="1" aria-pressed="false">{!! \app\document\service\ReaderPage::icon('thumb-up') !!}<span>有帮助</span></button>
    <button class="feedback-vote" type="button" data-feedback-vote="0" aria-pressed="false">{!! \app\document\service\ReaderPage::icon('thumb-down') !!}<span>无帮助</span></button>
  </div>
  <form class="feedback-form" data-feedback-form hidden>
    <fieldset>
      <legend>哪里需要改进？（可多选）</legend>
      @foreach(\app\document\service\FeedbackService::REASONS as $code => $label)<label class="feedback-reason"><input type="checkbox" name="reasons" value="{{ $code }}"><span>{{ $label }}</span></label>@endforeach
    </fieldset>
    <textarea name="comment" rows="3" maxlength="{{ \app\document\service\FeedbackService::COMMENT_MAX }}" placeholder="补充说明（选填），例如缺少哪一步、哪段示例报错" aria-label="补充说明"></textarea>
    <div class="feedback-actions"><button class="feedback-cancel" type="button" data-feedback-cancel>取消</button><button class="feedback-submit" type="submit">提交</button></div>
  </form>
  <p class="feedback-status" data-feedback-status role="status" aria-live="polite"></p>
</section>
