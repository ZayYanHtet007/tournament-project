<?php
include('partial/header.php');
?>

    <div class="faqheader">
             <h1 class="faq-h1">General FAQ</h1>
    </div>

    <div class="faq-search-container">
        <input type="text" id="faqSearch" class="faq-search-box" placeholder="Type to search questions...">
        <div class="faq-search-icon"></div>
    </div>

    <div class="faq-container" id="faqContainer">
        
        <div class="faq-item">
            <div class="faq-question">What is esports?</div>
            <div class="faq-answer">
                <p>Esports, short for electronic sports, refers to organized competitive video gaming. Players or teams compete in various video games for prizes and recognition, often in front of a live audience or online viewers.</p>
            </div>
        </div>

        <div class="faq-item active">
            <div class="faq-question">What types of games are played in esports?</div>
            <div class="faq-answer">
                <p>Esports, short for electronic sports, refers to organized competitive video gaming. Players or teams compete in various video games for prizes and recognition, often in front of a live audience or online viewers.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">Are there career opportunities in the gaming industry besides playing games?</div>
            <div class="faq-answer">
                <p>Esports, short for electronic sports, refers to organized competitive video gaming. Players or teams compete in various video games for prizes and recognition, often in front of a live audience or online viewers.</p>
            </div>
        </div>

        </div>

        <div class="no-results" id="noResults">
            No questions found matching your search.
        </div>
    </div>

   

    <script>
    
        const faqItems = document.querySelectorAll('.faq-item');

        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            const answer = item.querySelector('.faq-answer');

            
            if(item.classList.contains('active')) {
                answer.style.maxHeight = answer.scrollHeight + "px";
            }

            question.addEventListener('click', () => {
                const isActive = item.classList.contains('active');

            
                faqItems.forEach(otherItem => {
                    otherItem.classList.remove('active');
                    otherItem.querySelector('.faq-answer').style.maxHeight = null;
                });

                
                if (!isActive) {
                    item.classList.add('active');
                    answer.style.maxHeight = answer.scrollHeight + "px";
                }
            });
        });

       
        const searchInput = document.getElementById('faqSearch');
        const noResultsMsg = document.getElementById('noResults');

        searchInput.addEventListener('keyup', (e) => {
            const searchValue = e.target.value.toLowerCase();
            let hasResults = false;

            faqItems.forEach(item => {
                const questionText = item.querySelector('.faq-question').innerText.toLowerCase();
                const answerText = item.querySelector('.faq-answer').innerText.toLowerCase();
               
                if(questionText.includes(searchValue) || answerText.includes(searchValue)) {
                    item.style.display = 'block';
                    hasResults = true;
                } else {
                    item.style.display = 'none';
                }
            });


            if(hasResults) {
                noResultsMsg.style.display = 'none';
            } else {
                noResultsMsg.style.display = 'block';
            }
        });
    </script>



<?php
include('partial/footer.php');
?>