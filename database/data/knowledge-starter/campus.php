<?php

// Original AcadSyntra starter content: Campus & Student Life.

return [
    [
        'category' => 'Campus & Student Life',
        'type' => 'campus_guide',
        'title' => 'Surviving 100 Level: What Nobody Tells You',
        'excerpt' => 'Practical advice for your first year: registration, lectures, friendships, money and protecting your CGPA from day one.',
        'tags' => ['100 level', 'freshers', 'campus life'],
        'body' => <<<'HTML'
<p>Your first year sets the tone for your entire degree. Many students treat 100 level as a warm-up year, then spend the rest of their time trying to repair a weak CGPA. Here is what experienced students wish someone had told them.</p>

<h2>1. Your CGPA starts counting immediately</h2>
<p>Your 100 level results count towards your final class of degree. Courses that look easy, like general studies (GST), carry units too, and failing them means carryovers that follow you. Treat every course seriously from the first week.</p>

<h2>2. Finish registration early and keep copies</h2>
<ul>
<li>Pay fees and complete course registration well before the deadline. Late registration often attracts penalties, and some portals close without warning.</li>
<li>Print or save copies of your fee receipts, course registration form and any clearance documents. You will need them again, sometimes years later.</li>
<li>Check that every compulsory course for your department is on your form. A missing course can delay graduation.</li>
</ul>

<h2>3. Attend lectures, especially the first ones</h2>
<p>The first lectures usually cover the course outline, how the course will be assessed and what the lecturer expects. Some lecturers take attendance and give marks for it. Sit where you can see and hear clearly, and take your own notes instead of relying only on handouts.</p>

<h2>4. Know your continuous assessment</h2>
<p>Tests, assignments and practicals often make up a significant part of your final score. Students who ignore them are under huge pressure in the exam. Note every test date in your calendar the day it is announced.</p>

<h2>5. Choose your friends carefully</h2>
<p>The people you spend time with shape your habits. Look for classmates who attend lectures, ask questions and share information. Form a small study group of three to five serious people. Be friendly with everyone, but be careful about anyone who pressures you into risky activities.</p>

<h2>6. Learn to manage your own time</h2>
<p>At university, nobody tells you when to study. Make a simple weekly timetable that includes lectures, personal study, rest and activities. Even one or two focused hours of study a day from the first week makes exams much less stressful.</p>

<h2>7. Budget from the first allowance</h2>
<p>Write down your monthly allowance and your fixed costs: food, transport, data, toiletries. Set aside money for textbooks and handouts. Avoid lending more than you can afford to lose, and stay away from instant loan apps with high charges.</p>

<h2>8. Use the support available</h2>
<ul>
<li>Your <strong>course adviser</strong> can help with registration and course choices.</li>
<li>Most universities have a <strong>student affairs</strong> office and counselling services.</li>
<li>The <strong>library</strong> is a quiet place to study and has more resources than you think.</li>
<li>Senior students in your department can share past questions and advice.</li>
</ul>

<h2>9. Look after your health</h2>
<p>Register at the university clinic or health centre, sleep enough, eat properly and drink clean water. Many students fall sick during exams because they neglect themselves for weeks before.</p>

<h2>10. Enjoy it</h2>
<p>University is also about growing as a person. Join one or two clubs or associations that interest you, make friends, and try new things. Just make sure fun and learning go together.</p>
HTML,
        'questions' => [
            ['q' => 'Why should you take 100 level courses seriously?', 'options' => ['They do not count', 'They count towards your final class of degree', 'Only GST courses count', 'They are always easy'], 'answer' => 1, 'explain' => 'First-year results count towards your CGPA.'],
            ['q' => 'What should you keep after registration?', 'options' => ['Nothing', 'Copies of receipts and registration forms', 'Only your ID card', 'Your lecture timetable only'], 'answer' => 1, 'explain' => 'You will need receipts and forms again, sometimes years later.'],
            ['q' => 'What size of study group does the guide suggest?', 'options' => ['One person', 'Three to five serious people', 'The whole class', 'Twenty people'], 'answer' => 1, 'explain' => 'A small group of three to five serious classmates works best.'],
            ['q' => 'What does the guide say about instant loan apps?', 'options' => ['Use them often', 'Stay away from those with high charges', 'They are the best way to budget', 'They are free'], 'answer' => 1, 'explain' => 'High-charge loan apps can trap students in debt.'],
        ],
    ],
    [
        'category' => 'Campus & Student Life',
        'type' => 'campus_guide',
        'title' => 'How Your GPA and CGPA Are Calculated',
        'excerpt' => 'Course units, grade points, a worked GPA example and how your CGPA decides your class of degree.',
        'tags' => ['CGPA', 'GPA', 'grading system'],
        'body' => <<<'HTML'
<p>Many students only look at their CGPA when results come out. If you understand how it is calculated, you can plan which courses deserve the most effort and set realistic targets.</p>

<h2>Course units</h2>
<p>Each course has a number of <strong>units</strong> (also called credit units or credit load), usually from 1 to 4 or more. Units show how much a course counts. A 3-unit course affects your GPA three times as much as a 1-unit course.</p>

<h2>Grades and grade points</h2>
<p>Many Nigerian universities use a 5-point scale similar to this:</p>
<table>
<thead><tr><th>Score</th><th>Grade</th><th>Grade point</th></tr></thead>
<tbody>
<tr><td>70 to 100</td><td>A</td><td>5</td></tr>
<tr><td>60 to 69</td><td>B</td><td>4</td></tr>
<tr><td>50 to 59</td><td>C</td><td>3</td></tr>
<tr><td>45 to 49</td><td>D</td><td>2</td></tr>
<tr><td>40 to 44</td><td>E</td><td>1</td></tr>
<tr><td>0 to 39</td><td>F</td><td>0</td></tr>
</tbody>
</table>
<p>Grading systems differ between universities and change over time. Some no longer use the E grade, for example. Always check your own student handbook.</p>

<h2>Calculating your GPA</h2>
<p>For each course, multiply the units by the grade point to get the <strong>quality points</strong>. Add the quality points, then divide by the total units.</p>
<p><strong>GPA = total quality points ÷ total units</strong></p>
<h3>Worked example</h3>
<table>
<thead><tr><th>Course</th><th>Units</th><th>Grade</th><th>Point</th><th>Quality points</th></tr></thead>
<tbody>
<tr><td>MTH 101</td><td>3</td><td>A</td><td>5</td><td>15</td></tr>
<tr><td>CHM 101</td><td>3</td><td>B</td><td>4</td><td>12</td></tr>
<tr><td>GST 101</td><td>2</td><td>C</td><td>3</td><td>6</td></tr>
<tr><td>BIO 101</td><td>3</td><td>B</td><td>4</td><td>12</td></tr>
<tr><td>PHY 101</td><td>3</td><td>D</td><td>2</td><td>6</td></tr>
<tr><td><strong>Total</strong></td><td><strong>14</strong></td><td></td><td></td><td><strong>51</strong></td></tr>
</tbody>
</table>
<p>GPA = 51 ÷ 14 = <strong>3.64</strong> (to two decimal places).</p>

<h2>CGPA</h2>
<p>Your <strong>CGPA</strong> (cumulative GPA) uses all the courses you have taken so far: total quality points from every semester divided by total units from every semester. It is not simply the average of your semester GPAs, because semesters with more units count more.</p>

<h2>Class of degree</h2>
<p>Many universities use bands like these on the 5-point scale:</p>
<ul>
<li>First Class: 4.50 to 5.00</li>
<li>Second Class Upper: 3.50 to 4.49</li>
<li>Second Class Lower: 2.40 to 3.49</li>
<li>Third Class: 1.50 to 2.39</li>
<li>Pass: 1.00 to 1.49 (where still awarded)</li>
</ul>
<p>Again, check your own handbook. The exact bands can differ.</p>

<h2>What this means for your planning</h2>
<ul>
<li><strong>Units matter.</strong> An A in a 4-unit course lifts your GPA more than an A in a 1-unit course.</li>
<li><strong>Early semesters are easier to fix.</strong> As your total units grow, each new semester moves your CGPA less. That is why a strong start matters.</li>
<li><strong>Failed courses hurt twice:</strong> once when you fail, and again when you carry the course over and add its units again.</li>
</ul>
HTML,
        'questions' => [
            ['q' => 'How do you calculate GPA?', 'options' => ['Total units ÷ total quality points', 'Total quality points ÷ total units', 'Average of all scores', 'Number of A grades ÷ courses'], 'answer' => 1, 'explain' => 'GPA is total quality points divided by total units.'],
            ['q' => 'A 3-unit course with grade B (4 points) gives how many quality points?', 'options' => ['7', '12', '4', '3'], 'answer' => 1, 'explain' => '3 units × 4 points = 12.'],
            ['q' => 'What was the GPA in the worked example?', 'options' => ['3.40', '3.64', '4.00', '3.20'], 'answer' => 1, 'explain' => '51 ÷ 14 = 3.64.'],
            ['q' => 'Why is CGPA not just the average of semester GPAs?', 'options' => ['Because semesters with more units count more', 'Because only final year counts', 'Because GST is excluded', 'Because it uses scores, not points'], 'answer' => 0, 'explain' => 'CGPA uses total points over total units across all semesters.'],
        ],
    ],
    [
        'category' => 'Campus & Student Life',
        'type' => 'campus_guide',
        'title' => 'How to Write a Good SIWES Report',
        'excerpt' => 'A clear structure for your industrial training report, what to record during placement and mistakes that cost marks.',
        'tags' => ['SIWES', 'industrial training', 'report writing'],
        'body' => <<<'HTML'
<p>The Students Industrial Work Experience Scheme (SIWES) gives you practical experience in a real workplace. At the end, you submit a report that is assessed alongside your logbook and supervisor's evaluation. A good report is much easier to write if you prepare from your first day of placement.</p>

<h2>During your placement: collect material</h2>
<ul>
<li><strong>Fill your logbook every day,</strong> not at the end of the week. Write what you did, tools or equipment you used and what you learned.</li>
<li><strong>Take notes on the organisation:</strong> its history, structure, departments and what it produces or provides.</li>
<li><strong>Keep evidence</strong> such as diagrams you drew, procedures you followed and, where allowed, photographs of equipment. Always ask permission before taking photographs at work.</li>
<li><strong>Ask questions.</strong> Supervisors are usually happy to explain why things are done a certain way, and those explanations make strong report content.</li>
</ul>

<h2>A common report structure</h2>
<p>Departments have their own formats, so always follow the one you are given. A typical structure looks like this:</p>
<h3>Preliminary pages</h3>
<p>Title page, certification or approval page, dedication (optional), acknowledgement, abstract and table of contents.</p>
<h3>Chapter 1: Introduction</h3>
<p>A short background to SIWES, its objectives, and the aims of your own training.</p>
<h3>Chapter 2: About the organisation</h3>
<p>The company's history, mission, organisational structure (an organogram helps), departments and the unit where you worked.</p>
<h3>Chapter 3: Work done and experience gained</h3>
<p>This is the most important chapter. Describe the tasks you performed, the equipment and software you used and the procedures you followed. Link them to what you learned in class. Use headings for each major activity.</p>
<h3>Chapter 4: Challenges</h3>
<p>Problems you faced, such as limited equipment, transport or adapting to work hours, and how you handled them.</p>
<h3>Chapter 5: Conclusion and recommendations</h3>
<p>Summarise what you gained, and make practical recommendations to the school, the organisation and future students.</p>
<h3>References and appendices</h3>
<p>List any sources you used and attach diagrams or extra material as appendices.</p>

<h2>Writing tips</h2>
<ul>
<li>Write in the past tense and in your own words. Copying another student's report is easy for supervisors to spot.</li>
<li>Be specific. "I learned networking" is weak. "I configured IP addresses on twelve office computers and tested connections using the ping command" is strong.</li>
<li>Keep the abstract short, about 150 to 250 words, summarising where you trained, what you did and what you learned.</li>
<li>Label every figure and table, and mention it in the text.</li>
<li>Proofread, and ask a friend to read it. Check the font, spacing and margins your department requires.</li>
</ul>

<h2>Mistakes that cost marks</h2>
<ul>
<li>A logbook that does not match the report.</li>
<li>Long, copied descriptions of the company instead of your own experience.</li>
<li>No link between the work you did and your course of study.</li>
<li>Missing signatures and stamps from your industry-based supervisor.</li>
</ul>
HTML,
        'questions' => [
            ['q' => 'When should you fill your SIWES logbook?', 'options' => ['At the end of placement', 'Every day', 'Only on Fridays', 'Only when asked'], 'answer' => 1, 'explain' => 'Daily entries are accurate and match your report.'],
            ['q' => 'Which chapter is described as the most important?', 'options' => ['About the organisation', 'Work done and experience gained', 'Challenges', 'References'], 'answer' => 1, 'explain' => 'Chapter 3 shows what you actually did and learned.'],
            ['q' => 'Which statement is the stronger example of specific writing?', 'options' => ['"I learned networking."', '"I configured IP addresses on twelve computers and tested connections with ping."', '"Networking was good."', '"I did many things."'], 'answer' => 1, 'explain' => 'Specific tasks and tools make a report credible.'],
            ['q' => 'What should you do before photographing equipment at work?', 'options' => ['Nothing', 'Ask permission', 'Post it online', 'Hide the camera'], 'answer' => 1, 'explain' => 'Always ask permission before taking photographs at a workplace.'],
        ],
    ],
    [
        'category' => 'Campus & Student Life',
        'type' => 'campus_guide',
        'title' => 'Final-Year Project: The Five Chapters Explained',
        'excerpt' => 'What goes into the introduction, literature review, methodology, results and conclusion chapters, and how to stay on schedule.',
        'tags' => ['final year project', 'research', 'thesis writing'],
        'body' => <<<'HTML'
<p>Your final-year project is often the biggest piece of work in your degree. Most Nigerian undergraduate projects follow a five-chapter structure. Knowing what each chapter does makes the work far less overwhelming.</p>

<h2>Before chapter one: choose a good topic</h2>
<ul>
<li>Pick a topic you can finish in the time you have, with data you can actually collect.</li>
<li>Make it specific. "Unemployment in Nigeria" is too broad. "Factors affecting graduate unemployment in one state, 2018 to 2023" is manageable.</li>
<li>Discuss two or three options with your supervisor and agree before you start writing.</li>
</ul>

<h2>Chapter 1: Introduction</h2>
<p>This chapter tells the reader what the study is about and why it matters. It usually contains:</p>
<ul>
<li><strong>Background to the study:</strong> the context and what is already known.</li>
<li><strong>Statement of the problem:</strong> the gap or problem your study addresses. This is the heart of the chapter.</li>
<li><strong>Objectives:</strong> one general aim and a few specific objectives.</li>
<li><strong>Research questions and/or hypotheses:</strong> questions that match your objectives one to one.</li>
<li><strong>Significance of the study:</strong> who benefits from your findings.</li>
<li><strong>Scope and limitations:</strong> what the study covers and what it does not.</li>
<li><strong>Definition of terms:</strong> key words as used in your study.</li>
</ul>

<h2>Chapter 2: Literature review</h2>
<p>Here you show what other researchers have found. It usually includes a conceptual review (key concepts explained), a theoretical framework (the theory guiding your study) and an empirical review (summaries of previous studies and their findings). End with a short summary that shows the gap your study fills.</p>
<p>Do not just list studies one after another. Group them by theme, compare their findings and point out where they agree or disagree. Cite every source properly in the referencing style your department uses, such as APA.</p>

<h2>Chapter 3: Methodology</h2>
<p>This chapter explains exactly how you carried out the study, clearly enough for someone else to repeat it. It typically covers the research design, population, sample size and sampling technique, research instrument (for example a questionnaire), validity and reliability of the instrument, method of data collection and method of data analysis.</p>

<h2>Chapter 4: Results and discussion</h2>
<p>Present your data with tables and charts, analyse it with the methods you described in chapter three, and answer each research question or test each hypothesis in turn. Then discuss your findings: do they agree or disagree with the studies in chapter two, and why?</p>

<h2>Chapter 5: Summary, conclusion and recommendations</h2>
<p>Summarise the study and its main findings, state your conclusion and give practical recommendations based on what you found. Add suggestions for further research.</p>

<h2>Staying on schedule</h2>
<ul>
<li>Agree deadlines for each chapter with your supervisor.</li>
<li>Write chapter three early. Your data collection depends on it.</li>
<li>Back up your work in at least two places, such as cloud storage and a flash drive.</li>
<li>Keep your reference list as you go, not at the end.</li>
<li>Write in your own words. Universities check for plagiarism, and copied work can lead to failure.</li>
</ul>
HTML,
        'questions' => [
            ['q' => 'Which section is described as the heart of chapter one?', 'options' => ['Definition of terms', 'Statement of the problem', 'Scope', 'Significance'], 'answer' => 1, 'explain' => 'The statement of the problem explains the gap the study addresses.'],
            ['q' => 'What does the methodology chapter explain?', 'options' => ['What other researchers found', 'How the study was carried out', 'Recommendations', 'The final conclusion'], 'answer' => 1, 'explain' => 'Chapter 3 describes design, sampling, instruments and analysis.'],
            ['q' => 'How should a good literature review be organised?', 'options' => ['As a list of studies one after another', 'By theme, comparing findings', 'Alphabetically by author only', 'Without citations'], 'answer' => 1, 'explain' => 'Grouping by theme and comparing findings shows real understanding.'],
            ['q' => 'Why does the guide suggest writing chapter three early?', 'options' => ['It is the shortest', 'Data collection depends on it', 'Supervisors ignore it', 'It has no references'], 'answer' => 1, 'explain' => 'You need your methodology settled before collecting data.'],
        ],
    ],
    [
        'category' => 'Campus & Student Life',
        'type' => 'campus_guide',
        'title' => 'Managing Money as a Student',
        'excerpt' => 'A simple budget, tracking where your money goes, saving a little every month and avoiding common money traps.',
        'tags' => ['budgeting', 'personal finance', 'student life'],
        'body' => <<<'HTML'
<p>Running out of money halfway through the month is one of the most common stresses in university. You do not need a big allowance to manage well. You need a plan and a few habits.</p>

<h2>Step 1: know your income</h2>
<p>Write down everything you expect to receive each month: allowance, money from a part-time job or business, scholarships. If your allowance comes irregularly, plan with the smallest amount you can count on.</p>

<h2>Step 2: list your needs first</h2>
<p>Needs are things you cannot skip: food, transport, data for school work, toiletries, handouts and printing. Write an amount for each. These come first, every month.</p>

<h2>Step 3: a simple budget split</h2>
<p>One easy method is to divide your income into three parts. Adjust the percentages to your situation, but keep the idea:</p>
<ul>
<li><strong>Needs: about 70%.</strong> Food, transport, data, school costs.</li>
<li><strong>Wants: about 20%.</strong> Outings, snacks, entertainment.</li>
<li><strong>Savings: about 10%.</strong> Even ₦500 or ₦1,000 a month builds the habit and gives you an emergency cushion.</li>
</ul>
<p>Example: with ₦40,000 a month, that is ₦28,000 for needs, ₦8,000 for wants and ₦4,000 for savings.</p>

<h2>Step 4: track your spending</h2>
<p>For one month, write down every naira you spend, in a notebook or a phone note. Most students are surprised by where their money goes: small daily snacks, data, transport to places they could walk to. You cannot fix what you cannot see.</p>

<h2>Ways to make your money last</h2>
<ul>
<li><strong>Buy food items in bulk</strong> with friends and cook together instead of buying every meal.</li>
<li><strong>Use Wi-Fi</strong> where available and choose data bundles that match how much you actually use.</li>
<li><strong>Share textbooks</strong> or use the library before buying.</li>
<li><strong>Pay for needs first</strong> as soon as your allowance arrives, before spending on wants.</li>
<li><strong>Keep your savings separate,</strong> in a different account or savings feature, so you are not tempted.</li>
</ul>

<h2>Money traps to avoid</h2>
<ul>
<li><strong>Instant loan apps</strong> with very high charges and aggressive collection. A small loan can grow quickly.</li>
<li><strong>"Double your money" schemes</strong> and investment groups promising high returns in a short time. If it sounds too good to be true, it usually is.</li>
<li><strong>Betting</strong> as a way to make money. It is designed so most people lose over time.</li>
<li><strong>Lending money you cannot afford to lose,</strong> even to close friends.</li>
<li><strong>Sharing your bank PIN, OTP or card details</strong> with anyone, including people who claim to be from your bank.</li>
</ul>

<h2>Earning a little extra</h2>
<p>Many students earn from skills: graphic design, tutoring juniors, hair making, typing and printing, photography or selling items classmates need. Choose something that fits around your studies and never let it push your grades down.</p>
HTML,
        'questions' => [
            ['q' => 'In the guide\'s simple budget, roughly what share goes to needs?', 'options' => ['10%', '20%', '70%', '100%'], 'answer' => 2, 'explain' => 'About 70% for needs, 20% for wants and 10% for savings.'],
            ['q' => 'With ₦40,000 a month, how much goes to savings at 10%?', 'options' => ['₦400', '₦4,000', '₦8,000', '₦28,000'], 'answer' => 1, 'explain' => '10% of ₦40,000 is ₦4,000.'],
            ['q' => 'Why should you track your spending for a month?', 'options' => ['To impress friends', 'You cannot fix what you cannot see', 'Banks require it', 'To get a loan'], 'answer' => 1, 'explain' => 'Tracking reveals where your money really goes.'],
            ['q' => 'Which of these is a money trap according to the guide?', 'options' => ['Buying food in bulk with friends', 'Using the library', '"Double your money" schemes', 'Keeping savings separate'], 'answer' => 2, 'explain' => 'Schemes promising high quick returns are usually scams.'],
        ],
    ],
    [
        'category' => 'Campus & Student Life',
        'type' => 'campus_guide',
        'title' => 'Hostel Life: Living Well With Roommates',
        'excerpt' => 'How to agree house rules early, share space fairly, protect your belongings and handle conflict calmly.',
        'tags' => ['hostel', 'roommates', 'campus life'],
        'body' => <<<'HTML'
<p>Sharing a room with people you have just met can be one of the best or one of the most stressful parts of university. Most roommate problems come from expectations that were never discussed. A little planning in the first week prevents most of them.</p>

<h2>Agree on basic rules in the first week</h2>
<p>Sit together and agree on a few simple things. Write them down if it helps:</p>
<ul>
<li><strong>Cleaning:</strong> a rota for sweeping, taking out refuse and cleaning shared spaces.</li>
<li><strong>Quiet hours:</strong> for example no loud music or calls after 10pm and during exam periods.</li>
<li><strong>Visitors:</strong> how often, how late, and whether overnight guests are allowed (and what hostel rules say).</li>
<li><strong>Sharing:</strong> what is shared (a kettle, cleaning items) and what is personal.</li>
<li><strong>Electricity and cooking:</strong> follow hostel rules on appliances. Some hostels ban hot plates and cooking in rooms because of fire risk.</li>
</ul>

<h2>Respect space and belongings</h2>
<ul>
<li>Ask before using anything that is not yours, even something small.</li>
<li>Keep your side tidy. Your mess affects everyone.</li>
<li>Be mindful of light and noise when others are sleeping or studying. Use earphones.</li>
</ul>

<h2>Keep your things safe</h2>
<ul>
<li>Use a padlock on your locker or box, and keep valuables like laptops and phones locked away when you go out.</li>
<li>Do not leave your phone charging unattended in a shared area.</li>
<li>Mark your items, for example with your name on chargers and plates.</li>
<li>Lock the door when you leave, even for a short time.</li>
</ul>

<h2>Handling conflict</h2>
<p>Disagreements will happen. How you handle them matters more than the issue itself.</p>
<ol>
<li><strong>Talk early</strong>, before frustration builds up. Small issues become big ones when ignored.</li>
<li><strong>Talk privately and calmly</strong>, not in front of other people or in a group chat.</li>
<li><strong>Describe the problem, not the person:</strong> "The room has not been swept this week" works better than "You are dirty".</li>
<li><strong>Suggest a solution</strong> and listen to theirs.</li>
<li><strong>Involve the hall warden or porter</strong> if the problem continues or involves safety, theft or harassment.</li>
</ol>

<h2>Health and hygiene</h2>
<ul>
<li>Keep food covered and dispose of leftovers quickly to avoid rats and cockroaches.</li>
<li>Open windows for ventilation and sleep under a mosquito net.</li>
<li>Do not share personal items such as toothbrushes, razors or towels.</li>
</ul>

<h2>Be a roommate people want to live with</h2>
<p>Greet people, share information about lectures and opportunities, offer help when someone is sick, and keep your promises about cleaning and noise. Many lifelong friendships start in hostel rooms.</p>
HTML,
        'questions' => [
            ['q' => 'When does the guide suggest agreeing on room rules?', 'options' => ['After the first argument', 'In the first week', 'Before exams only', 'Never'], 'answer' => 1, 'explain' => 'Early agreement prevents most roommate problems.'],
            ['q' => 'Which is the better way to raise a problem?', 'options' => ['"You are dirty."', '"The room has not been swept this week."', 'Post it in the group chat', 'Ignore it'], 'answer' => 1, 'explain' => 'Describe the problem, not the person.'],
            ['q' => 'Why do some hostels ban hot plates in rooms?', 'options' => ['Noise', 'Fire risk', 'They are expensive', 'They attract visitors'], 'answer' => 1, 'explain' => 'Cooking appliances in rooms can cause fires.'],
            ['q' => 'When should you involve the hall warden?', 'options' => ['For every small issue', 'When a problem continues or involves safety or theft', 'Never', 'Only during exams'], 'answer' => 1, 'explain' => 'Escalate persistent problems and anything involving safety.'],
        ],
    ],
    [
        'category' => 'Campus & Student Life',
        'type' => 'campus_guide',
        'title' => 'Staying Safe On and Around Campus',
        'excerpt' => 'Everyday safety habits, common scams that target students, online safety and what to do in an emergency.',
        'tags' => ['safety', 'scams', 'campus life'],
        'body' => <<<'HTML'
<p>Most students go through university without serious problems, but a few simple habits make you much safer. This guide covers personal safety, scams that target students, online safety and emergencies.</p>

<h2>Everyday safety habits</h2>
<ul>
<li><strong>Move in groups at night,</strong> and stick to well-lit, busy routes.</li>
<li><strong>Tell someone where you are going</strong> and when you expect to return, especially if you are travelling or meeting someone new.</li>
<li><strong>Use known transport.</strong> Avoid entering vehicles that already have several strangers inside, especially at night.</li>
<li><strong>Keep your phone charged</strong> and save emergency contacts, including your hall warden and campus security.</li>
<li><strong>Do not display expensive items</strong> unnecessarily in isolated places.</li>
</ul>

<h2>Scams that target students</h2>
<ul>
<li><strong>Fake hostel or apartment agents</strong> who collect payment for rooms that do not exist or belong to someone else. Inspect the room, confirm with the landlord or hostel office, and get a receipt.</li>
<li><strong>Admission, result or "upgrade" fixers</strong> who promise to change grades or secure admission for money. These are frauds, and dealing with them can get you expelled.</li>
<li><strong>Fake job and scholarship offers</strong> that ask you to pay a fee first. Genuine scholarships do not ask winners to pay to receive them.</li>
<li><strong>Bank and payment scams:</strong> calls or messages asking for your PIN, OTP, BVN or card details. Your bank will never ask for your PIN or OTP.</li>
<li><strong>"Double your money" investment schemes</strong> that promise high returns quickly.</li>
</ul>

<h2>Be careful about pressure groups</h2>
<p>Be cautious of anyone who pressures you to join a secret group, promises protection or power, or asks you to attend initiation meetings. Secret cults are banned in Nigerian universities and membership can lead to expulsion, prosecution and serious danger. If you feel pressured or threatened, speak to the student affairs office, a trusted lecturer or campus security.</p>

<h2>Online safety</h2>
<ul>
<li>Use strong, different passwords for your email, school portal and banking apps, and turn on two-step verification.</li>
<li>Do not share your portal login with anyone, including people offering to help you register.</li>
<li>Think before posting your location, room number or travel plans publicly.</li>
<li>Be careful with people you meet online. Meet in public places and tell a friend.</li>
<li>Never send money or intimate pictures to someone you have only met online.</li>
</ul>

<h2>Drinks and parties</h2>
<p>At parties, keep your drink with you, do not accept opened drinks from strangers, and leave with the friends you came with. Look out for each other.</p>

<h2>In an emergency</h2>
<ul>
<li>Call <strong>112</strong>, Nigeria's national emergency number, and campus security.</li>
<li>Know the location of your campus clinic and the nearest hospital.</li>
<li>In a fire, leave the building immediately by the nearest exit and do not go back for belongings.</li>
<li>Report crimes and threats to campus security and, where appropriate, the police.</li>
</ul>

<p>Staying safe is not about being afraid. It is about small habits that keep you in control, so you can focus on your studies and enjoy campus life.</p>
HTML,
        'questions' => [
            ['q' => 'What should you never share with someone claiming to be from your bank?', 'options' => ['Your name', 'Your PIN or OTP', 'Your department', 'Your hostel'], 'answer' => 1, 'explain' => 'Banks never ask for your PIN or OTP.'],
            ['q' => 'How can you avoid fake hostel agents?', 'options' => ['Pay quickly before others do', 'Inspect the room and confirm with the landlord or hostel office', 'Pay in cash without a receipt', 'Trust online adverts'], 'answer' => 1, 'explain' => 'Inspection and confirmation protect you from fake agents.'],
            ['q' => 'What is Nigeria\'s national emergency number?', 'options' => ['911', '999', '112', '123'], 'answer' => 2, 'explain' => '112 is the national emergency number.'],
            ['q' => 'A scholarship asks you to pay a fee before you receive it. What does the guide say?', 'options' => ['Pay it quickly', 'Genuine scholarships do not ask winners to pay', 'All scholarships charge fees', 'Ask a friend to pay'], 'answer' => 1, 'explain' => 'Upfront fees are a common sign of a scam.'],
        ],
    ],
];
