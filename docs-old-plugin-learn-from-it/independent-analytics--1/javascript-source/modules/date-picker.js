const $ = jQuery;

const DatePicker = {
    datePicker: null,
    selectingStartDate: true,
    inputs: null,
    inputsKeyboardMode: null,
    prevMonth: null,
    currentMonth: null,
    fastTravelButtons: null,
    days: null,
    setup: function() {
        var self = this;
        this.datePicker = $('#iawp-date-picker');
        this.inputs = {
            'start': $('#iawp-start-date'), 
            'end': $('#iawp-end-date')
        };
        this.inputsKeyboardMode = {
            'start': $('#iawp-start-date-keyboard'),
            'end': $('#iawp-end-date-keyboard')
        };
        this.prevMonth = $('.iawp-calendar-month.iawp-previous');
        this.currentMonth = $('.iawp-calendar-month.iawp-current');
        this.fastTravelButtons = {
            'start': $('.iawp-fast-travel.prev-month'),
            'end': $('.iawp-fast-travel.current-month')
        };
        this.days = $('.iawp-day:not(.empty)');

        this.watchClicksOnDays();
        this.watchClicksOnRelativeRangeButtons();
        this.watchClicksOnNavigationButtons();
        this.watchClicksOnFastTravelButtons();
        this.watchHoverEventsOnDays();
        this.watchChangesToDate();
        this.watchChangesToKeyboardInputs();
        $('#dates-button, #cancel-date').on('click', function() {
            self.toggleModal();
        });
        $('.modal-background').on('click', function() {
            if ($('#modal-dates').hasClass('show')) {
                self.toggleModal();
            }
        });
        this.datePicker.on('click', '#iawp-start-date, #iawp-end-date', function() {
            if ($(this).hasClass('iawp-active'))
                return;
            self.toggleInputs();
        });
        $('#apply-date').on('click', function() {
            self.apply();
        });
        $('.keyboard-input').on('focus', function() {
            self.datePicker.addClass('keyboard-mode');
        });
    },
    watchClicksOnDays: function(){
        var self = this;
        this.datePicker.on('click', '.iawp-day', function() {
            if (self.selectingStartDate && $(this).hasClass('iawp-start')) {
                self.toggleInputs();
                return;
            }
            if (!self.selectingStartDate && $(this).hasClass('iawp-end')) {
                self.toggleInputs();
                return;
            }
            self.changeRangeCustomDates($(this));
        });
    },
    watchClicksOnRelativeRangeButtons: function() {
        var self = this;
        $('.iawp-date-range-buttons button').on('click', function() {
            if ($(this).hasClass('active'))
                return;

            self.changeRangeRelativeDates($(this));
        });
    },
    watchClicksOnNavigationButtons: function() {
        var self = this;
        this.datePicker.on('click', '.iawp-prev-month-nav', function() {
            self.navigateMonths(false);
        });
        this.datePicker.on('click', '.iawp-next-month-nav', function() {
            self.navigateMonths(true);
        });
    },
    watchClicksOnFastTravelButtons: function() {
        var self = this;
        $('.iawp-fast-travel').on('click', function() {
            var start = $(this).hasClass('prev-month') ? true : false;
            self.fastTravel($(this).data('month'), start);
        });
    },
    watchHoverEventsOnDays: function() {
        var self = this;
        this.datePicker.on('mouseenter', '.iawp-day', function() {
            if ($(this).hasClass('in-range') || $(this).hasClass('iawp-start') || $(this).hasClass('iawp-end'))
                return;
            self.updateInputBasedOnHover($(this));
        });
    },
    watchChangesToDate: function() {
        var self = this;
        for (let input in this.inputs){
            this.inputs[input].on('date-changed', function() {
                self.updateFastTravelButtons();
                self.updateInRange();
                $(this).hasClass('iawp-start-date') ? self.toggleInputs() : self.toggleInputs(true);
            });
        }
    },
    watchChangesToKeyboardInputs: function() {
        var self = this;
        for (let input in this.inputsKeyboardMode){
            this.inputsKeyboardMode[input].on('change', function() {
                $('.iawp-date-range-buttons .active').removeClass('active');
                self.datePicker.data('relative-range', '')
            });
        }
    },
    changeRangeCustomDates: function(day) {
        if (this.selectingStartDate) {
            this.changeStartOrEndDate('start', day);
        } else {
            this.changeStartOrEndDate('end', day);
        }
        $('.iawp-date-range-buttons .active').removeClass('active');
        this.datePicker.data('relative-range', '')
    },
    changeStartOrEndDate: function(bound, clicked) {
        $('.iawp-day.iawp-' + bound).removeClass('iawp-'+ bound);
        var day = clicked.addClass('iawp-'+ bound);
        this.inputs[bound].val(day.data('display-date'));
        this.inputs[bound].data('date', day.data('date')).trigger('date-changed');
        if (bound == 'start') {
            if (new Date(this.inputs[bound].data('date')) > new Date(this.inputs[this.oppositeBound(bound)].data('date'))) {
                this.changeStartOrEndDate('end', day);
            }
        } else {
            if (new Date(this.inputs[bound].data('date')) < new Date(this.inputs[this.oppositeBound(bound)].data('date'))) {
                this.changeStartOrEndDate('start', day);
            }
        } 
    },
    changeRangeRelativeDates: function(button) {
        $('.iawp-date-range-buttons .active').removeClass('active');
        button.addClass('active');
        this.datePicker.data('relative-range', button.data('relative-range-id'))
        this.changeStartAndEndDate(button);
    },
    changeStartAndEndDate: function(button) {
        const bounds = ['start', 'end'];
        for (let key in bounds) {
            const bound = bounds[key];
            var formatted = button.data('relative-range-' + bound);
            var display = button.data('display-date-' + bound);
            var elemClass = 'iawp-'+ bound;
            $('.'+ elemClass).removeClass(elemClass);
            $('.iawp-day[data-date="'+ formatted +'"').addClass(elemClass);
            this.inputs[bound].val(display);
            this.inputs[bound].data('date', formatted).trigger('date-changed');
            // Update keyboard mode inputs
            this.inputsKeyboardMode[bound].val(formatted);
        }
    },
    updateInRange: function() {
        var self = this;
        $('.iawp-day.in-range').removeClass('in-range');
        this.days.each(function() {
            if (new Date($(this).data('date')) >= new Date(self.inputs.end.data('date')))
                return;

            if (new Date($(this).data('date')) > new Date(self.inputs.start.data('date'))) {
                $(this).addClass('in-range');
            }  
        });
    },
    updateInputBasedOnHover: function(day) {
        day.data('date') < this.inputs.start.data('date') ? this.toggleInputs(true) : this.toggleInputs(false);
    },
    toggleInputs: function(forceStart) {
        if (forceStart) {
            this.inputs.start.addClass('iawp-active');
            this.inputs.end.removeClass('iawp-active');
            this.selectingStartDate = true;
        } else if (forceStart === false) {
            this.inputs.start.removeClass('iawp-active');
            this.inputs.end.addClass('iawp-active');
            this.selectingStartDate = false;
        } else {
            this.inputs.start.toggleClass('iawp-active');
            this.inputs.end.toggleClass('iawp-active');
            this.selectingStartDate = !this.selectingStartDate;
        }
    },
    toggleModal: function() {
        $('#modal-dates').toggleClass('show');
        $('#iawp-layout').toggleClass('modal-open');
    },
    oppositeBound: function(bound) {
        return bound == 'start' ? 'end' : 'start';
    },
    navigateMonths: function(next) {
        if (next) {
            this.currentMonth.removeClass('iawp-current');
            this.currentMonth = this.currentMonth.next().addClass('iawp-current');
            this.prevMonth.removeClass('iawp-previous');
            this.prevMonth = this.prevMonth.next().addClass('iawp-previous');
        } else {
            this.prevMonth.removeClass('iawp-previous');
            this.prevMonth = this.prevMonth.prev().addClass('iawp-previous');
            this.currentMonth.removeClass('iawp-current');
            this.currentMonth = this.currentMonth.prev().addClass('iawp-current');
        }
    },
    fastTravel: function(month, start) {
        var targetMonth = $('.iawp-calendar-month[data-month="'+ month +'"]');

        if (start && targetMonth.next().length === 0)
            targetMonth = targetMonth.prev();
        if (!start && targetMonth.prev().length === 0)
            targetMonth = targetMonth.next();

        $('.iawp-calendar-month').removeClass('iawp-previous iawp-current');
        if (start) {
            this.prevMonth = targetMonth.addClass('iawp-previous');
            this.currentMonth = targetMonth.next().addClass('iawp-current');
        } else {
            this.currentMonth = targetMonth.addClass('iawp-current');
            this.prevMonth = targetMonth.prev().addClass('iawp-previous');
        }
    },
    updateFastTravelButtons: function() {
        this.fastTravelButtons.start.data('month', $('#iawp-start-date').data('date').slice(0, -3));
        this.fastTravelButtons.end.data('month', $('#iawp-end-date').data('date').slice(0, -3));
    },
    apply: function() {
        let detail = {}
        const relativeRange = this.datePicker.data('relative-range');
        if (relativeRange != '') {
            detail = {relativeRangeId: relativeRange}
        } else {
            if (this.datePicker.hasClass('keyboard-mode')) {
                detail = {
                    exactStart: this.inputsKeyboardMode.start.val(),
                    exactEnd: this.inputsKeyboardMode.end.val()
                }
            } else {
                detail = {
                    exactStart: this.inputs.start.data('date'),
                    exactEnd: this.inputs.end.data('date')
                }
            }
        }
        this.toggleModal();
        document.dispatchEvent(
            new CustomEvent('iawp:changeDates', {
                detail
            })
        )
    }
}

export { DatePicker };