# Example QA Criteria – Life Insurance Sales Calls

These criteria are my own, written for this project because no criteria were
provided by the company. They are based on common UK life-insurance sales
compliance requirements (FCA ICOBS rules, cooling-off obligations, suitability
assessment). Any QA manager can replace or extend them through the template
builder in the UI.

## Template: Standard Life Insurance Sales Call

**pass_threshold:** 80
**Description:** Evaluates agent performance on compliance, needs discovery, product explanation, and professionalism for inbound/outbound life insurance calls.

---

## Criteria

### 1. Opening – Agent Identification
- **Type:** pass_fail
- **Weight:** 2
- **is_critical:** false
- **phase_hint:** opening
- **Description:** Agent clearly states their full name and the company name within the first 30 seconds.
- **Fail conditions:** Agent does not state name OR does not state company name.

---

### 2. Opening – Call Recording Disclosure ⚠️ CRITICAL
- **Type:** yes_no_na
- **Weight:** 3
- **is_critical:** true
- **phase_hint:** opening
- **Description:** Agent informs the customer that the call may be recorded before collecting any personal data or discussing any product.
- **Fail conditions:** No recording disclosure is given, or it is given after personal data has been collected.

---

### 3. Opening – Purpose of Call
- **Type:** pass_fail
- **Weight:** 2
- **is_critical:** false
- **phase_hint:** opening
- **Description:** Agent clearly explains the reason for the call (e.g. follow-up on enquiry, renewal discussion, new quote).
- **Fail conditions:** Customer is not told why the agent is calling before product discussion begins.

---

### 4. Needs Discovery – Age and Health Status
- **Type:** pass_fail
- **Weight:** 3
- **is_critical:** false
- **phase_hint:** discovery
- **Description:** Agent asks for the customer's age and any significant health conditions relevant to life insurance underwriting before recommending a product.
- **Fail conditions:** No age asked, or product recommended before basic health/age information is collected.

---

### 5. Needs Discovery – Existing Cover and Dependants
- **Type:** pass_fail
- **Weight:** 2
- **is_critical:** false
- **phase_hint:** discovery
- **Description:** Agent asks whether the customer has any existing life cover and how many financial dependants they have.
- **Fail conditions:** Neither existing cover nor dependants are discussed.

---

### 6. Needs Discovery – Budget
- **Type:** pass_fail
- **Weight:** 2
- **is_critical:** false
- **phase_hint:** discovery
- **Description:** Agent asks or explores what monthly premium the customer is comfortable paying before quoting a figure.
- **Fail conditions:** Premium is quoted cold with no budget discussion.

---

### 7. Product Explanation – Coverage Amount and Term
- **Type:** pass_fail
- **Weight:** 3
- **is_critical:** false
- **phase_hint:** pitch
- **Description:** Agent clearly states the coverage amount (sum assured) and the policy term (or that it is whole-of-life) being quoted.
- **Fail conditions:** Coverage amount or term is not stated during the pitch.

---

### 8. Compliance – No Guaranteed-Approval Claims ⚠️ CRITICAL
- **Type:** yes_no_na
- **Weight:** 4
- **is_critical:** true
- **phase_hint:** anywhere
- **Description:** Agent never claims or implies that the application is guaranteed to be approved or that cover is guaranteed regardless of health history.
- **Fail conditions:** Agent uses phrases such as "guaranteed approval", "you're definitely covered", "no medical questions", or similar.

---

### 9. Compliance – No Pressure Tactics ⚠️ CRITICAL
- **Type:** yes_no_na
- **Weight:** 4
- **is_critical:** true
- **phase_hint:** anywhere
- **Description:** Agent does not use high-pressure, urgency-manufacturing, or fear-based language to push the customer into a decision (e.g. "this offer expires today", "without this your family will be left with nothing").
- **Fail conditions:** Agent uses artificial urgency, excessive fear language, or refuses to give the customer time to think.

---

### 10. Compliance – Cancellation / Cooling-Off Period
- **Type:** pass_fail
- **Weight:** 3
- **is_critical:** false
- **phase_hint:** close
- **Description:** Agent explains that the customer can cancel within the cooling-off period and get a full refund (30 days for UK life insurance, which is a pure protection contract under FCA ICOBS 7.1; called the free-look period in the US, usually 10 to 30 days depending on the state), before any payment is taken or application is submitted.
- **Fail conditions:** Cooling-off period is not mentioned before close or payment step.

---

### 11. Close – Clear Next Steps
- **Type:** pass_fail
- **Weight:** 2
- **is_critical:** false
- **phase_hint:** close
- **Description:** Agent summarises what happens next (e.g. application sent by email, direct debit set up, underwriting process) before ending the call.
- **Fail conditions:** Call ends without any explanation of what the customer should expect next.

---

### 12. Professionalism and Tone
- **Type:** scale_1_5
- **Weight:** 2
- **is_critical:** false
- **phase_hint:** anywhere
- **Description:** Agent is polite, patient, and clear throughout the call. Speaks at an appropriate pace, does not talk over the customer, and handles objections calmly.
- **Scoring guide:** 5 = exemplary; 4 = good with minor lapses; 3 = acceptable; 2 = noticeable issues; 1 = poor.
- **Fail conditions:** N/A (scale type; low scores reduce overall percentage).

---

### 13. Extract – Premium Quoted (informational)
- **Type:** extract
- **Weight:** 0
- **is_critical:** false
- **phase_hint:** pitch
- **Description:** Record the exact monthly premium figure quoted to the customer. This is for audit purposes only and does not affect the call score.
- **Fail conditions:** N/A

---

## Notes for seeder
- Criteria 2, 8, 9 are `is_critical = true`.
- Criteria 2, 8, 9 are `yes_no_na` type — a "no" answer triggers auto-fail.
- Criterion 12 is `scale_1_5`.
- Criterion 13 is `extract` — informational only, weight 0, excluded from score.
- Total scorable weight = 2+3+2+3+2+2+3+4+4+3+2+2 = 32 (criterion 13 excluded).
