-- Last Edit on 11/4/2025 by Hayden Weber


-- The folliwing tables are part of the 
-- Data Collection and Preperation (SR-1.1 -- SR-1.4)

-- QUESTIONABLE DIFFERENCES FROM ERD DIAGRAM
-- TagReview does not have a FK from User

drop table user cascade constraints;

create table User
(USER_ID                    number(5),
 user_role                  varchar2(30) not null,
 constraint user_pk primary key (USER_ID)
);

drop table TagReview cascade constraints;

create table TagReview
(REVIEW_ID                  number(5),
 assignment_id              number(5) not null,
 tag_status                 varchar2(20) not null,
 constraint tag_review_pk primary key (REVIEW_ID),
 constraint assignment_id_fk foreign key (assignment_id) 
    references TagAssignment(ASSIGNMENT_ID)
);

drop table Abstract cascade constraints;

create table Abstract
(ABSTRACT_ID                number(5),
 title                      varchar2(40) not null,
 content                    varchar2(40),
 constraint abscract_pk primary key (ABSTRACT_ID)
);

drop table Tag cascade constraints;

create table Tag
(TAG_ID                     number(5),
 label                      varchar2(30),
 constraint tag_pk primary key (TAG_ID)
);

drop table TagAssignment cascade constraints;

create table TagAssignment
(ASSIGNMENT_ID              number(5),
 user_id                    number(5),
 abstract_id                number(5),
 tag_id                     number(5),
 constraint tag_assignment_pk primary key (ASSIGNMENT_ID),
 constraint tag_assignmnet_fk_user foreign key (user_id)
    references User(USER_ID),
 constraint tag_assignmnet_fk_abstract foreign key (abstract_id)
    references Abstract(ABSTRACT_ID),
 constraint tag_assignmnet_fk_tag foreign key (tag_id)
    references Tag(TAG_ID)
);



-- The folliwing tables are part of the 
-- Model Training and Deployment (SR-2.1 -- SR-2.3)

-- QUESTIONABLE DIFFERENCES FROM ERD DIAGRAM
-- TrainingJob does not have a FK from User
-- ModelRegistry does not have a FK from TrainingJob
-- Unsure what data type to put for model_metrics as JSONB
--      is not a built in data type

drop table TrainingJob cascade constraints;

create table TrainingJob
(JOB_ID                 number(5),
 start_time             timestamp(),
 end_time               timestamp(),
 training_status        varchar2(20),
 constraint training_job_pk primary key (JOB_ID)
);

drop table ModelRegistry cascade constraints;

create table ModelRegistry
(MODEL_ID               number(5),
 model_version          varchar2(10),
 model_metrics          varchar2(10),
 model_path             varchar2(50),
 model_is_active        boolean,
 model_trained_on       timestamp(),
 constraint model_registry_pk primary key (MODEL_ID)
);

-- The folliwing tables are part of the 
-- Front-End User Interface (SR-3.1 -- SR-3.5)

drop table Submission cascade constraints;

create table Submission
(SUBMISSION_ID          number(5),
 abstract_text          varchar2(50),
 Submission_timestamp   timestamp(),
 constraint submisson_pk primary key (SUBMISSION_ID)
);

drop table Predciton cascade constraints;

create table Predciton
(PREDCITON_ID           number(5),
 submisson_id           number(5),
 tag_label              varchar2(50),
 confidence             float(5, 4),
 constraint predciton_pk primary key (PREDICTION_ID),
 constraint submisson_id_fk_submission foreign key (submisson_id)
    references Submission(SUBMISSION_ID)
);

-- QUESTIONABLE DIFFERENCES FROM ERD DIAGRAM
-- need to add UserAction to the diagram

drop table UserAction cascade constraints;

create table UserAction
(ACTION_ID              number(5),
 submisson_id           number(5),
 action_tyoe            varchar2(20),
 action_timestamp       timestamp(),
 constraint user_action_pk primary key (ACTION_ID),
 constraint submisson_id_fk_submission foreign key (submisson_id)
    references Submission(SUBMISSION_ID)
);

commit;