/**
 * FULL MCQ DATASET AND LOGIC
 * Extracted from Grade.pdf
 */

const MCQ_DATA = {
    "Grade 10": [
        { id: 1, category: "Mathematics", question: "If α and β are the zeros of the polynomial f(x)=x^2-5x+k such that α - β = 1, find the value of k.", options: ["6", "12", "4", "0"], answer: "6" },
        { id: 2, category: "Mathematics", question: "The angle of elevation of the top of a tower from a point on the ground, 30m away from the foot, is 30°. The height is:", options: ["10√3 m", "15√3 m", "30√3 m", "10 m"], answer: "10√3 m" },
        { id: 3, category: "Mathematics", question: "Two dice are thrown simultaneously. What is the probability of getting a sum of 8?", options: ["1/12", "5/36", "1/6", "1/9"], answer: "5/36" },
        { id: 4, category: "Mathematics", question: "In an Arithmetic Progression, if a=3.5, d=0, n=101, then a_n will be:", options: ["0", "3.5", "103.5", "104.5"], answer: "3.5" },
        { id: 5, category: "Mathematics", question: "The HCF of two numbers is 27 and their LCM is 162. If one number is 54, what is the other?", options: ["36", "45", "9", "81"], answer: "81" },
        { id: 6, category: "Physics", question: "The image formed by a concave mirror is virtual, erect, and larger than the object. Where is the object?", options: ["Between focus and center of curvature", "At the center of curvature", "Beyond the center of curvature", "Between the pole and principal focus"], answer: "Between the pole and principal focus" },
        { id: 7, category: "Physics", question: "Electrical resistivity of a given metallic wire depends upon:", options: ["Its length", "Its thickness", "Its shape", "Nature of the material"], answer: "Nature of the material" },
        { id: 8, category: "Chemistry", question: "In the reaction 3Fe(s)+4H2O(g) -> Fe3O4(s)+4H2(g), which is correct?", options: ["Iron is getting oxidized", "Water is getting oxidized", "Water is a reducing agent", "Water is an oxidizing agent"], answer: "Iron is getting oxidized" },
        { id: 9, category: "Chemistry", question: "Ethane, with the molecular formula C2H6, has:", options: ["6 covalent bonds", "7 covalent bonds", "8 covalent bonds", "9 covalent bonds"], answer: "7 covalent bonds" },
        { id: 10, category: "Biology", question: "Breakdown of pyruvate to give CO2, water, and energy takes place in:", options: ["Cytoplasm", "Mitochondria", "Chloroplast", "Nucleus"], answer: "Mitochondria" },
        { id: 11, category: "Biology", question: "Which of the following is a plant hormone?", options: ["Insulin", "Thyroxin", "Oestrogen", "Cytokinin"], answer: "Cytokinin" },
        { id: 12, category: "Social Science", question: "Who said, 'When France sneezes, the rest of Europe catches cold'?", options: ["Garibaldi", "Bismarck", "Mazzini", "Duke Metternich"], answer: "Duke Metternich" },
        { id: 13, category: "Social Science", question: "Which one of the following is a leguminous crop?", options: ["Pulses", "Jawar", "Millets", "Sesame"], answer: "Pulses" },
        { id: 14, category: "Social Science", question: "Power sharing is desirable because it helps:", options: ["Increase pressure on government", "Reduce possibilities of conflict", "Generate awareness", "Increase percentage of voters"], answer: "Reduce possibilities of conflict" },
        { id: 15, category: "Social Science", question: "In which sector do workers enjoy job security?", options: ["Unorganized", "Agriculture", "Organized", "Private"], answer: "Organized" },
        { id: 16, category: "Logical Reasoning", question: "Missing number in series: 2, 6, 12, 20, ?, 42", options: ["25", "28", "30", "32"], answer: "30" },
        { id: 17, category: "Logical Reasoning", question: "Identify relationship: 'Reading' : 'Knowledge' :: 'Work' : ?", options: ["Money", "Engagement", "Experience", "Employment"], answer: "Experience" },
        { id: 18, category: "Logical Reasoning", question: "Syllogism: All flowers are trees. No fruit is a tree. Conclusion:", options: ["No fruit is a flower", "Some trees are fruits", "All flowers are fruits", "None"], answer: "No fruit is a flower" },
        { id: 19, category: "Verbal Ability", question: "Choose the synonym for 'Candid':", options: ["Secretive", "Frank", "Uncertain", "Rude"], answer: "Frank" },
        { id: 20, category: "Verbal Ability", question: "Spot the error: 'One of the boys are missing from the class.'", options: ["One of", "the boys", "are missing", "from the class"], answer: "are missing" },
        { id: 21, category: "General Awareness", question: "Which Indian state has the highest literacy rate as per 2011 census?", options: ["Maharashtra", "Kerala", "Tamil Nadu", "Karnataka"], answer: "Kerala" },
        { id: 22, category: "Data Interpretation", question: "If a graph shows a steep upward slope in price vs time, it indicates:", options: ["Price stability", "Inflation/Price rise", "Deflation", "No change"], answer: "Inflation/Price rise" },
        { id: 23, category: "General Awareness", question: "Which body supervises the functioning of formal sources of loans in India?", options: ["Central Govt", "State Govt", "RBI", "SBI"], answer: "RBI" },
        { id: 24, category: "Logical Reasoning", question: "If CAT is coded as 3120, how is DOG coded?", options: ["4157", "4120", "3157", "4158"], answer: "4157" },
        { id: 25, category: "Logical Reasoning", question: "If South-East becomes North, North-East becomes West, what will West become?", options: ["North-East", "North-West", "South-East", "South-West"], answer: "South-East" },
        { id: 26, category: "General Awareness", question: "What is the full form of GDP?", options: ["Gross Domestic Product", "Gross Daily Product", "Global Domestic Product", "Great Domestic Product"], answer: "Gross Domestic Product" },
        { id: 27, category: "General Awareness", question: "Resource Planning involves:", options: ["Identification", "Planning structure", "Matching with national plans", "All of the above"], answer: "All of the above" },
        { id: 28, category: "General Awareness", question: "Which soil is ideal for growing cotton?", options: ["Alluvial", "Black", "Red", "Laterite"], answer: "Black" },
        { id: 29, category: "English", question: "Choose preposition: 'He is addicted ___ gambling.'", options: ["with", "of", "to", "on"], answer: "to" },
        { id: 30, category: "Logical Reasoning", question: "If P is brother of Q, Q is sister of R, and R is father of S, how is P related to S?", options: ["Father", "Uncle", "Brother", "Grandfather"], answer: "Uncle" }
    ],
    "Grade 11": [
        { id: 1, category: "Engineering", question: "Physics: A ball thrown upward at 20 m/s. Max height (g=10):", options: ["10m", "20m", "40m", "5m"], answer: "20m" },
        { id: 2, category: "Engineering", question: "Chemistry: Moles of solute in 1 kg of solvent is:", options: ["Molarity", "Normality", "Molality", "Formality"], answer: "Molality" },
        { id: 3, category: "Engineering", question: "Math: Value of sin(15°):", options: ["(√3-1)/2√2", "(√3+1)/2√2", "√3/2", "1/√2"], answer: "(√3-1)/2√2" },
        { id: 4, category: "Engineering", question: "Physics: Error in radius 2%, error in volume calculation is:", options: ["4%", "6%", "8%", "2%"], answer: "6%" },
        { id: 5, category: "Engineering", question: "Chemistry: Quantum number determining orbital shape:", options: ["Principal", "Azimuthal", "Magnetic", "Spin"], answer: "Azimuthal" },
        { id: 6, category: "Engineering", question: "Math: Number of subsets of A = {1, 2, 3}:", options: ["3", "6", "8", "9"], answer: "8" },
        { id: 7, category: "Medical", question: "Biology: Not a feature of Kingdom Monera:", options: ["Peptidoglycan wall", "Nuclear membrane", "Autotrophic/Heterotrophic", "Prokaryotic"], answer: "Nuclear membrane" },
        { id: 8, category: "Medical", question: "Biology: In cockroach, air enters through:", options: ["Lungs", "Gills", "Spiracles", "Skin"], answer: "Spiracles" },
        { id: 9, category: "Medical", question: "Chemistry: Hybridization of Carbon in Ethene (C2H4):", options: ["sp", "sp2", "sp3", "dsp2"], answer: "sp2" },
        { id: 10, category: "Medical", question: "Physics: Dimensions of Gravitational Constant (G):", options: ["[M^-1 L^3 T^-2]", "[M L^2 T^-2]", "[M^-1 L^2 T^-2]", "[M L^3 T^-2]"], answer: "[M^-1 L^3 T^-2]" },
        { id: 11, category: "Medical", question: "Biology: Unit of the kidney is:", options: ["Neuron", "Nephron", "Alveoli", "Villi"], answer: "Nephron" },
        { id: 12, category: "Medical", question: "Chemistry: Shape of NH3 molecule (VSEPR):", options: ["Tetrahedral", "Trigonal Planar", "Pyramidal", "Linear"], answer: "Pyramidal" },
        { id: 13, category: "Management", question: "Quant: If 30% A = 0.25 B = 1/5 C, then A:B:C is:", options: ["10:12:15", "10:15:12", "12:15:10", "15:12:10"], answer: "10:12:15" },
        { id: 14, category: "Management", question: "Verbal: Opposite of 'ABUNDANT':", options: ["Plentiful", "Scarce", "Ample", "Lavish"], answer: "Scarce" },
        { id: 15, category: "Management", question: "Logic: All cars are cats. All fans are cats. Conclusion: Some fans are cars.", options: ["True", "False", "Maybe", "Irrelevant"], answer: "Maybe" },
        { id: 16, category: "Management", question: "Quant: Average of first 50 natural numbers:", options: ["25", "25.5", "26", "50.5"], answer: "25.5" },
        { id: 17, category: "Management", question: "Verbal: 'To spill the beans' means:", options: ["Clumsy", "Reveal a secret", "Cook food", "Talk loudly"], answer: "Reveal a secret" },
        { id: 18, category: "Management", question: "Logic: Man's father is my father's son (no siblings):", options: ["Own", "Son's", "Father's", "Nephew's"], answer: "Son's" },
        { id: 19, category: "Law", question: "Legal: Company liable for driver's personal detour hit?", options: ["Yes", "No (Course of employment)", "Yes (Owner)", "No (Pedestrian fault)"], answer: "No (Course of employment)" },
        { id: 20, category: "Law", question: "Constitution: Preamble amended by which Act?", options: ["42nd", "44th", "73rd", "86th"], answer: "42nd" },
        { id: 21, category: "Law", question: "Logic: Lawyer : Justice :: Doctor : ?", options: ["Hospital", "Medicine", "Treatment/Cure", "Nurse"], answer: "Treatment/Cure" },
        { id: 22, category: "Law", question: "Current Affairs: Current Chief Justice of India (2024 Context):", options: ["D.Y. Chandrachud", "Sanjiv Khanna", "U.U. Lalit", "N.V. Ramana"], answer: "Sanjiv Khanna" },
        { id: 23, category: "Law", question: "English: 'Tort' origin:", options: ["Latin", "French", "Greek", "German"], answer: "French" },
        { id: 24, category: "Law", question: "Legal: What is 'Mens Rea'?", options: ["Guilty Act", "Guilty Mind", "Punishment", "Investigation"], answer: "Guilty Mind" },
        { id: 25, category: "Design", question: "Color: Which is a cool color?", options: ["Red", "Orange", "Blue", "Yellow"], answer: "Blue" },
        { id: 26, category: "Design", question: "Observation: Cylinder from top shape:", options: ["Rectangle", "Circle", "Ellipse", "Triangle"], answer: "Circle" },
        { id: 27, category: "Design", question: "General: Pink City:", options: ["Udaipur", "Jodhpur", "Jaipur", "Bikaner"], answer: "Jaipur" },
        { id: 28, category: "Design", question: "Logo: 'Swoosh' belongs to:", options: ["Adidas", "Puma", "Nike", "Reebok"], answer: "Nike" },
        { id: 29, category: "Design", question: "Material: Fabric from flax plant:", options: ["Cotton", "Silk", "Linen", "Wool"], answer: "Linen" },
        { id: 30, category: "Design", question: "Perspective: Receding lines converge to:", options: ["Horizon", "Vanishing Point", "Station Point", "Ground"], answer: "Vanishing Point" }
    ],
    "Grade 12": [
        { id: 1, category: "Engineering", question: "Math: Integral of dx / x(x^n + 1):", options: ["1/n log|x^n/(x^n+1)|", "log|x^n/(x^n+1)|", "1/n log|(x^n+1)/x^n|", "None"], answer: "1/n log|x^n/(x^n+1)|" },
        { id: 2, category: "Engineering", question: "Physics: Flux of electric field through closed surface enclosing capacitor:", options: ["CV/ε0", "2CV/ε0", "Zero", "CV"], answer: "Zero" },
        { id: 3, category: "Engineering", question: "Chemistry: Strongest acid:", options: ["HCOOH", "CH3COOH", "ClCH2COOH", "FCH2COOH"], answer: "FCH2COOH" },
        { id: 4, category: "Engineering", question: "Math: If matrix A is symmetric and skew-symmetric:", options: ["Diagonal", "Null", "Unit", "Triangular"], answer: "Null" },
        { id: 5, category: "Engineering", question: "Physics: Slit halved, dist doubled. Fringe width becomes:", options: ["Halved", "Doubled", "Quadrupled", "Unchanged"], answer: "Quadrupled" },
        { id: 6, category: "Engineering", question: "Chemistry: Coordination number of Co in [Co(en)2Cl2]+:", options: ["4", "5", "6", "2"], answer: "6" },
        { id: 7, category: "Medical", question: "Biology: IUD releasing Copper ions:", options: ["Lippes Loop", "CuT", "LNG-20", "Progestasert"], answer: "CuT" },
        { id: 8, category: "Medical", question: "Biology: Restriction enzymes class:", options: ["Ligases", "Polymerases", "Nucleases", "Isomerases"], answer: "Nucleases" },
        { id: 9, category: "Medical", question: "Chemistry: Aqueous basic strength of methyl substituted amines:", options: ["2° > 1° > 3°", "3° > 2° > 1°", "1° > 2° > 3°", "2° > 3° > 1°"], answer: "2° > 1° > 3°" },
        { id: 10, category: "Medical", question: "Physics: Time for 20% to 80% decay (half-life 20m):", options: ["20 min", "40 min", "30 min", "25 min"], answer: "40 min" },
        { id: 11, category: "Medical", question: "Biology: Megaspores from megaspore mother cell process:", options: ["Megagametogenesis", "Megasporogenesis", "Microsporogenesis", "Parthenocarpy"], answer: "Megasporogenesis" },
        { id: 12, category: "Medical", question: "Chemistry: Vitamin for blood coagulation:", options: ["A", "C", "K", "D"], answer: "K" },
        { id: 13, category: "Management", question: "Quant: Boat 6 km/h, current 2 km/h. 3h return trip. Distance:", options: ["6 km", "8 km", "10 km", "12 km"], answer: "8 km" },
        { id: 14, category: "Management", question: "Logical: Should exams be objective? I (Fairness), II (Expression):", options: ["I strong", "II strong", "Both strong", "Neither"], answer: "Both strong" },
        { id: 15, category: "Management", question: "DI: Exp 20% more than Income. Loss percentage:", options: ["20%", "16.66%", "25%", "Unknown"], answer: "16.66%" },
        { id: 16, category: "Management", question: "Verbal: INDIGENT : WEALTHY ::", options: ["Angry:Rich", "Native:Affluent", "Gauche:Graceful", "Scholarly:Erudite"], answer: "Gauche:Graceful" },
        { id: 17, category: "Management", question: "Quant: Pipes A(15) and B(20). A off after 4m. Total time:", options: ["10:40", "11:45", "12:20", "14:40"], answer: "14:40" },
        { id: 18, category: "Management", question: "Logical: Odd one out:", options: ["Circle:Arc", "Chair:Leg", "Flower:Petal", "Cover:Page"], answer: "Cover:Page" },
        { id: 19, category: "Law", question: "Legal: A writes letter to B (theft). B reads. Defamation?", options: ["Libel", "Slander", "No (No 3rd party)", "Contempt"], answer: "No (No 3rd party)" },
        { id: 20, category: "Law", question: "Constitution: Article abolishing Untouchability:", options: ["16", "17", "18", "23"], answer: "17" },
        { id: 21, category: "Law", question: "Contract Law: Agreement enforceable by law:", options: ["Promise", "Contract", "Obligation", "Lawful object"], answer: "Contract" },
        { id: 22, category: "Law", question: "Logical: 256(you are good), 637(we are bad), 358(good and bad). 'and' is:", options: ["2", "5", "8", "3"], answer: "8" },
        { id: 23, category: "Law", question: "GK: International Court of Justice location:", options: ["Geneva", "The Hague", "New York", "Paris"], answer: "The Hague" },
        { id: 24, category: "Law", question: "Legal: 'Volenti non fit injuria' means:", options: ["Willing person, no injury", "Actionable injury", "No excuse", "None"], answer: "Willing person, no injury" },
        { id: 25, category: "Design", question: "Creative: Serif font example:", options: ["Arial", "Helvetica", "Times New Roman", "Comic Sans"], answer: "Times New Roman" },
        { id: 26, category: "Design", question: "GK: Designer of old Indian Parliament:", options: ["Corbusier", "Lutyens/Baker", "Correa", "Baker"], answer: "Lutyens/Baker" },
        { id: 27, category: "Design", question: "Material: Terracotta origin:", options: ["Plastic", "Clay", "Glass", "Metal"], answer: "Clay" },
        { id: 28, category: "Design", question: "Color: Red + Blue = ?", options: ["Green", "Orange", "Violet/Purple", "Brown"], answer: "Violet/Purple" },
        { id: 29, category: "Design", question: "Spatial: 3D shape with 6 square faces:", options: ["Tetrahedron", "Cube", "Cylinder", "Cone"], answer: "Cube" },
        { id: 30, category: "Design", question: "Branding: Tagline 'Just Do It':", options: ["Adidas", "Nike", "Under Armour", "Apple"], answer: "Nike" }
    ]
};

/**
 * Assessment Logic Controller
 */
class MCQAssessment {
    constructor(data) {
        this.allData = data;
        this.currentGrade = null;
        this.score = 0;
        this.history = [];
    }

    // Set the grade level for the user
    setGrade(gradeName) {
        if (this.allData[gradeName]) {
            this.currentGrade = gradeName;
            this.score = 0;
            this.history = [];
            return true;
        }
        return false;
    }

    // Get all questions for the current selected grade
    getQuestions() {
        return this.allData[this.currentGrade] || [];
    }

    // Process a user's answer
    submitAnswer(questionId, userAnswer) {
        const questions = this.getQuestions();
        const question = questions.find(q => q.id === questionId);

        if (!question) return { error: "Question not found" };

        const isCorrect = question.answer === userAnswer;
        if (isCorrect) this.score++;

        this.history.push({
            questionId,
            userAnswer,
            correctAnswer: question.answer,
            isCorrect
        });

        return { isCorrect, correctAnswer: question.answer };
    }

    // Calculate final results
    getResults() {
        return {
            grade: this.currentGrade,
            totalQuestions: this.getQuestions().length,
            score: this.score,
            percentage: (this.score / this.getQuestions().length) * 100,
            summary: this.history
        };
    }
}

// EXAMPLE USAGE:
// const quiz = new MCQAssessment(MCQ_DATA);
// quiz.setGrade("Grade 10");
// console.log(quiz.submitAnswer(1, "6")); // Correct
// console.log(quiz.getResults());