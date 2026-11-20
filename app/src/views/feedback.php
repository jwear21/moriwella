<p><a class="link" href="<?= url('/settings') ?>">&larr; Me</a></p>
<h1 class="page-title">Send feedback</h1>
<p class="subtle">What worked, what confused you, what you wish it did. Short is fine.</p>
<section class="card write">
  <form method="post">
    <?= csrf_field() ?>
    <label for="fpage">Which screen? (optional)</label>
    <input id="fpage" name="page" maxlength="120" placeholder="For example: Today, Lists, Experiences">
    <label for="fmsg">Your feedback</label>
    <textarea id="fmsg" name="message" rows="7" required></textarea>
    <button class="btn" type="submit">Send</button>
  </form>
</section>
