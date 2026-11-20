<?php
/* Starter experience templates. Sections are suggestions: prompts are gentle, never required.
   Every section can be renamed, removed or added to once an experience is created. */
function starter_templates(): array {
    $s = fn(array $pairs) => array_map(fn($k, $v) => ['name' => $k, 'prompt' => $v], array_keys($pairs), array_values($pairs));
    return [
        'cruise' => ['name' => 'Cruise vacation', 'blurb' => 'Ship, ports, excursions, dining and sea days.', 'sections' => $s([
            'Embarkation' => 'How did boarding day go? First look at the ship?',
            'Sea days' => 'How did you spend your days at sea?',
            'Ports' => 'Where did you dock, and what stood out?',
            'Excursions' => 'What did you book or discover on shore?',
            'Dining' => 'What did you eat, and where?',
            'Shows and activities' => 'What did you see or do onboard?',
            'People we met' => 'Who did you meet along the way?',
            'Disembarkation' => 'How did it end?'])],
        'vacation' => ['name' => 'Destination vacation', 'blurb' => 'A trip to one place, from arrival to heading home.', 'sections' => $s([
            'Getting there' => 'How was the journey?', 'Where we stayed' => 'What was the place like?',
            'Things we did' => 'What filled your days?', 'Food and drink' => 'What was worth remembering?',
            'Favorite moments' => 'What do you want to keep?', 'Heading home' => 'How did the trip wrap up?'])],
        'vegas' => ['name' => 'Las Vegas weekend', 'blurb' => 'Shows, food, and the stories you will retell.', 'sections' => $s([
            'Arrival' => 'First impressions?', 'Shows' => 'What did you see?', 'Food' => 'Best meal of the weekend?',
            'Nights out' => 'How did the evenings go?', 'Wins and funny moments' => 'What made you laugh?', 'Heading home' => 'How did it end?'])],
        'roadtrip' => ['name' => 'Road trip', 'blurb' => 'Stops, stays, detours and the soundtrack.', 'sections' => $s([
            'The route' => 'Where did the road take you?', 'Stops' => 'Where did you pause?', 'Where we stayed' => 'Where did you sleep?',
            'Roadside finds' => 'Any good detours?', 'Favorite moments' => 'What do you want to keep?'])],
        'camping' => ['name' => 'Camping and the outdoors', 'blurb' => 'Campsite, trails, weather and campfire moments.', 'sections' => $s([
            'Setting up camp' => 'What was the site like?', 'Trails and adventures' => 'What did you explore?',
            'Wildlife and scenery' => 'What did you see?', 'Campfire moments' => 'What stories were told?', 'Packing up' => 'How did it end?'])],
        'wedding' => ['name' => 'Wedding', 'blurb' => 'Planning, the day itself, and the celebration.', 'sections' => $s([
            'Getting ready' => 'How did the day begin?', 'The ceremony' => 'What do you want to remember?',
            'The celebration' => 'How did the party go?', 'Toasts and words' => 'What was said?', 'Guests and family' => 'Who was there?', 'The details' => 'Flowers, music, food, anything you loved.'])],
        'anniversary' => ['name' => 'Anniversary', 'blurb' => 'How you marked the day, and what it means.', 'sections' => $s([
            'How we celebrated' => 'What did you do?', 'What I love about us' => 'What is true this year?',
            'Gifts and surprises' => 'Anything special?', 'Looking ahead' => 'What are you looking forward to?'])],
        'birthday' => ['name' => 'Milestone birthday', 'blurb' => 'A big birthday and the people who made it special.', 'sections' => $s([
            'The day' => 'How did it go?', 'Who was there' => 'Who came?', 'Gifts and cards' => 'What touched you?',
            'Words I want to keep' => 'Messages, toasts, little moments.', 'Looking back and ahead' => 'What does this number mean to you?'])],
        'graduation' => ['name' => 'Graduation', 'blurb' => 'The ceremony, the people and what comes next.', 'sections' => $s([
            'The ceremony' => 'How did it feel?', 'Family and friends' => 'Who celebrated with you?',
            'Looking back' => 'What are you proud of?', 'What comes next' => 'What is ahead?'])],
        'reunion' => ['name' => 'Reunion', 'blurb' => 'School, college or family: who came and what you remembered.', 'sections' => $s([
            'Who was there' => 'Who did you see?', 'Stories we told' => 'What did everyone remember?',
            'Then and now' => 'What has changed?', 'Photos and keepsakes' => 'What was shared?', 'Until next time' => 'What was promised?'])],
        'concert' => ['name' => 'Concert or festival', 'blurb' => 'Lineup, favorite moments and who you went with.', 'sections' => $s([
            'Before the show' => 'How did the day start?', 'Highlights' => 'What did you love?',
            'With friends' => 'Who was there?', 'Food and fun' => 'What else filled the day?', 'The ride home' => 'How did you feel afterward?'])],
        'sports' => ['name' => 'Sports event', 'blurb' => 'The game, the crowd and the memories.', 'sections' => $s([
            'Pre-game' => 'How did the day begin?', 'The game' => 'What happened?', 'The crowd' => 'What was the atmosphere?', 'Best moments' => 'What do you want to keep?'])],
        'convention' => ['name' => 'Convention or conference', 'blurb' => 'Sessions, people and ideas worth keeping.', 'sections' => $s([
            'Arrival' => 'How did it start?', 'Sessions' => 'What did you learn?', 'People I met' => 'Who was worth remembering?',
            'Ideas and takeaways' => 'What will you do next?', 'After hours' => 'What happened outside the sessions?'])],
        'holiday' => ['name' => 'Holiday gathering', 'blurb' => 'Traditions, meals and the people around the table.', 'sections' => $s([
            'Preparing' => 'What went into getting ready?', 'The meal' => 'What was on the table?',
            'Traditions' => 'What did you do the way you always do?', 'Who was there' => 'Who gathered?', 'Little moments' => 'What stood out?'])],
        'milestone' => ['name' => 'Life milestone', 'blurb' => 'A new home, a new baby, a new chapter.', 'sections' => $s([
            'The moment' => 'What happened?', 'How it felt' => 'What is going through your mind?',
            'People who were there' => 'Who shared it with you?', 'What changes now' => 'What is different?'])],
        'blank' => ['name' => 'Start from scratch', 'blurb' => 'Name your own sections.', 'sections' => []],
    ];
}
